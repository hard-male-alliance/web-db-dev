"""Real HTTP acceptance checks; requires a seeded, disposable application DB.

Run: python tests/application_journey.py --base-url http://127.0.0.1:8080
The suite deliberately writes uniquely marked demonstration records.
"""
from __future__ import annotations

import argparse
import html
from html.parser import HTMLParser
import http.cookiejar
import json
import re
import unittest
import urllib.error
import urllib.parse
import urllib.request
import uuid


class Form(HTMLParser):
    def __init__(self):
        super().__init__()
        self.fields = {}

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('name'):
            self.fields[attrs['name']] = attrs.get('value', '')


class Browser:
    def __init__(self, base):
        self.base = base.rstrip('/')
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def request(self, path, data=None):
        encoded = None if data is None else urllib.parse.urlencode(data, doseq=True).encode()
        try:
            response = self.opener.open(self.base + path, encoded, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read()
            return response.status, body.decode('utf-8', errors='replace'), dict(response.headers), body

    def post(self, path, data, csrf=True):
        if csrf:
            status, body, _, _ = self.request(path)
            assert status == 200, (path, status, body[:200])
            form = Form()
            form.feed(body)
            assert '_csrf' in form.fields, (path, 'missing CSRF field')
            data = dict(data, _csrf=form.fields['_csrf'])
        return self.request(path, data)

    def login(self, username, password):
        return self.post('/?r=site/login', {'LoginForm[username]': username, 'LoginForm[password]': password})


class ApplicationJourney(unittest.TestCase):
    base_url = ''

    def setUp(self):
        self.browser = Browser(self.base_url)

    def admin(self):
        status, body, _, _ = self.browser.login('admin', 'admin123456')
        self.assertEqual(status, 200, body[:500])
        status, body, _, _ = self.browser.request('/admin/?r=site/index')
        self.assertEqual(status, 200, body[:500])

    def test_public_pages_and_private_files(self):
        for path in ['/?r=site/index', '/?r=site/members', '/?r=site/projects', '/?r=site/project&id=1', '/?r=site/updates', '/?r=site/message', '/?r=site/submissions']:
            with self.subTest(path=path):
                status, body, _, _ = self.browser.request(path)
                self.assertEqual(status, 200, body[:300])
                self.assertNotIn('Internal Server Error', body)
        for path in ['/data/install.sql', '/.env', '/common/config/main.php', '/data/team/../install.sql']:
            with self.subTest(private=path):
                status, body, _, _ = self.browser.request(path)
                self.assertNotIn('CREATE TABLE', body)
                self.assertNotIn('<?php', body)
        status, body, _, _ = self.browser.request('/health')
        self.assertEqual(status, 200, body)
        self.assertIsInstance(json.loads(body), dict)

    def test_authentication_access_control_and_csrf(self):
        status, body, _, _ = self.browser.request('/admin/?r=site/index')
        self.assertIn('LoginForm[username]', body)
        status, body, _, _ = self.browser.login('admin', 'not-the-password')
        self.assertIn('LoginForm[username]', body)
        status, _, _, _ = self.browser.post('/?r=site/message', {'MessageForm[name]': 'csrf', 'MessageForm[body]': 'must not save'}, csrf=False)
        self.assertEqual(status, 400)
        status, _, _, _ = self.browser.login('member', 'member123456')
        self.assertEqual(status, 200)
        self.assertEqual(self.browser.request('/admin/?r=site/profile')[0], 200)
        for action in ['team', 'members', 'member&id=1', 'messages', 'update&id=1']:
            with self.subTest(action=action):
                self.assertEqual(self.browser.request('/admin/?r=site/' + action)[0], 403)
        self.assertEqual(self.browser.request('/admin/?r=site/logout')[0], 405)
        self.assertEqual(self.browser.post('/admin/?r=site/logout', {}, csrf=False)[0], 400)
        form = Form()
        form.feed(self.browser.request('/admin/?r=site/index')[1])
        self.assertEqual(self.browser.request('/admin/?r=site/logout', {'_csrf': form.fields['_csrf']})[0], 200)
        self.assertIn('LoginForm[username]', self.browser.request('/admin/?r=site/profile')[1])

    def test_message_moderation_and_reply(self):
        marker = 'journey-' + uuid.uuid4().hex[:12]
        payload = marker + ' <script>alert("x")</script> 中文🌟'
        status, body, _, _ = self.browser.post('/?r=site/message', {'MessageForm[name]': marker, 'MessageForm[email]': 'private@example.test', 'MessageForm[body]': payload, 'MessageForm[website]': '', 'MessageForm[status]': 'approved'})
        self.assertEqual(status, 200, body[:500])
        public = self.browser.request('/?r=site/message')[1]
        self.assertNotIn(marker, public, 'Unmoderated visitor input must not be public')
        self.admin()
        body = self.browser.request('/admin/?r=site/messages')[1]
        self.assertIn(marker, body)
        # The moderation form is inside the card containing our unique marker.
        rows = re.findall(r'<article\b[^>]*>.*?</article>', body, flags=re.S)
        row = next(row for row in rows if marker in row)
        match = re.search(r'action="([^"]*(?:moderate)[^"]*)"', row)
        self.assertIsNotNone(match, row)
        route = html.unescape(match.group(1))
        form = Form()
        form.feed(row)
        status, body, _, _ = self.browser.request(route, {'_csrf': form.fields['_csrf'], 'status': 'approved', 'reply': marker + ' reply'})
        self.assertEqual(status, 200, body[:500])
        public = Browser(self.base_url).request('/?r=site/message')[1]
        self.assertIn(marker, public)
        self.assertIn(marker + ' reply', public)
        self.assertIn('&lt;script&gt;', public)
        self.assertNotIn('<script>alert("x")</script>', public)
        self.assertNotIn('private@example.test', public)

    def test_message_boundaries(self):
        for name, body in [('', 'abc'), ('n' * 81, 'abc'), ('valid', ''), ('valid', 'ab'), ('valid', 'x' * 2001)]:
            with self.subTest(name_length=len(name), body_length=len(body)):
                status, page, _, _ = self.browser.post('/?r=site/message', {'MessageForm[name]': name, 'MessageForm[body]': body, 'MessageForm[website]': ''})
                self.assertIn(status, [200, 422])
                self.assertRegex(page, r'has-error|invalid-feedback|help-block')
        for name_length, body_length in [(1, 3), (80, 2000)]:
            with self.subTest(valid_name=name_length, valid_body=body_length):
                visitor = Browser(self.base_url)
                status, page, _, _ = visitor.post('/?r=site/message', {
                    'MessageForm[name]': '界' * name_length, 'MessageForm[body]': '🌟' * body_length,
                    'MessageForm[website]': '',
                })
                self.assertEqual(status, 200)
                self.assertIn('留言已送达', page)

    def test_profile_edit_and_project_publication(self):
        marker = 'publish-' + uuid.uuid4().hex[:12]
        self.browser.login('member', 'member123456')
        status, body, _, _ = self.browser.post('/admin/?r=site/profile', {
            'RecordForm[name]': '验证成员', 'RecordForm[title]': marker,
            'RecordForm[bio]': 'HTTP profile journey 中文🌟', 'RecordForm[email]': 'member@example.test',
            'RecordForm[role]': 'admin', 'RecordForm[id]': '1',
        })
        self.assertEqual(status, 200, body[:500])
        self.assertIn(marker, Browser(self.base_url).request('/?r=site/members')[1])
        self.assertEqual(self.browser.request('/admin/?r=site/team')[0], 403, 'Profile mass assignment must not elevate role')
        self.browser = Browser(self.base_url)
        self.admin()
        data = {'RecordForm[title]': marker, 'RecordForm[summary]': marker + ' summary',
                'RecordForm[description]': 'HTTP-created draft then publication', 'RecordForm[status]': 'active',
                'RecordForm[published]': '0', 'RecordForm[progress]': '0', 'RecordForm[started_on]': '2026-10-19',
                'RecordForm[member_ids][]': ['1', '2']}
        status, body, _, _ = self.browser.post('/admin/?r=site/project', data)
        self.assertEqual(status, 200, body[:500])
        self.assertNotIn(marker, Browser(self.base_url).request('/?r=site/projects')[1])
        body = self.browser.request('/admin/?r=site/projects')[1]
        self.assertIn(marker, body)
        row = next(row for row in re.findall(r'<tr\b[^>]*>.*?</tr>', body, re.S) if marker in row)
        route = html.unescape(re.search(r'href="([^"]*site(?:%2F|/)project(?:&amp;|&)id=\d+)"', row).group(1))
        missing_csrf = dict(data, **{'RecordForm[published]': '1'})
        self.assertEqual(self.browser.post(route, missing_csrf, csrf=False)[0], 400)
        self.assertNotIn(marker, Browser(self.base_url).request('/?r=site/projects')[1])
        for progress in ['-1', '101', '1.5', 'not-an-integer']:
            with self.subTest(invalid_progress=progress):
                data['RecordForm[progress]'] = progress
                status, page, _, _ = self.browser.post(route, data)
                self.assertIn(status, [200, 422])
                self.assertRegex(page, r'has-error|invalid-feedback|help-block')
        data['RecordForm[progress]'] = '0'
        data['RecordForm[title]'] = '界' * 201
        status, page, _, _ = self.browser.post(route, data)
        self.assertIn(status, [200, 422])
        self.assertRegex(page, r'has-error|invalid-feedback|help-block')
        data['RecordForm[title]'] = marker
        data.update({'RecordForm[published]': '1', 'RecordForm[progress]': '100'})
        status, body, _, _ = self.browser.post(route, data)
        self.assertEqual(status, 200, body[:500])
        public = Browser(self.base_url).request('/?r=site/projects')[1]
        self.assertIn(marker, public)
        # This project excludes member 3; the role checks use a fresh member session.
        project_id = urllib.parse.parse_qs(urllib.parse.urlsplit(route).query)['id'][0]
        status, body, _, _ = self.browser.post('/admin/?r=site/update', {
            'RecordForm[title]': marker + ' update', 'RecordForm[body]': marker + ' update body',
            'RecordForm[project_id]': project_id, 'RecordForm[published]': '1',
        })
        self.assertEqual(status, 200, body[:500])
        self.assertIn(marker + ' update', Browser(self.base_url).request('/?r=site/updates')[1])
        # Remove the ordinary member, then prove ownership is checked server-side.
        data['RecordForm[member_ids][]'] = ['1']
        self.assertEqual(self.browser.post(route, data)[0], 200)
        member = Browser(self.base_url)
        self.assertEqual(member.login('member', 'member123456')[0], 200)
        self.assertEqual(member.request(route)[0], 403)
        denied = member.post('/admin/?r=site/update', {
            'RecordForm[title]': marker + ' forbidden', 'RecordForm[body]': 'must not attach to unassigned project',
            'RecordForm[project_id]': project_id, 'RecordForm[published]': '1',
        })
        self.assertIn(denied[0], [200, 422])
        self.assertRegex(denied[1], r'has-error|invalid-feedback|help-block')
        self.assertNotIn(marker + ' forbidden', Browser(self.base_url).request('/?r=site/updates')[1])

    def test_document_downloads(self):
        for identifier in range(1, 7):
            with self.subTest(document=identifier):
                status, _, headers, raw = self.browser.request('/?r=site/download&id=' + str(identifier))
                self.assertEqual(status, 200)
                self.assertTrue(raw.startswith(b'%PDF-'), raw[:100])
                self.assertIn('attachment', headers.get('Content-Disposition', ''))
        for value in ['0', '-1', '999999', '../install.sql', '1 OR 1=1']:
            with self.subTest(invalid=value):
                status, _, _, _ = self.browser.request('/?r=site/download&id=' + urllib.parse.quote(value))
                self.assertIn(status, [400, 404])


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-url', default='http://127.0.0.1:8080')
    args, remaining = parser.parse_known_args()
    ApplicationJourney.base_url = args.base_url
    unittest.main(argv=['application_journey.py', *remaining], verbosity=2)
