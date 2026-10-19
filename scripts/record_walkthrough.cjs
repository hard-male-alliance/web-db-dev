/** Record a real browser walkthrough against an isolated seeded demo database. */
'use strict';
const {chromium} = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const base = process.argv[2] || 'http://127.0.0.1:8080';
const evidence = path.join(root, '.cache', 'browser');
const screenshots = path.join(root, 'docs', 'validation', 'screenshots');
const output = path.join(root, 'data', 'team', '星河工坊_操作演示.webm');
const marker = `录屏演示 ${new Date().toISOString().replace(/[:.]/g, '-')}`;

async function main() {
  fs.mkdirSync(evidence, {recursive: true});
  fs.mkdirSync(screenshots, {recursive: true});
  const browser = await chromium.launch({headless: true});
  const context = await browser.newContext({viewport: {width: 1440, height: 960},
    recordVideo: {dir: evidence, size: {width: 1440, height: 960}}});
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {if (message.type() === 'error') errors.push(message.text());});
  const visit = async route => {
    const response = await page.goto(base + route, {waitUntil: 'networkidle'});
    assert.equal(response.status(), 200, route);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Overflow: ${route}`);
    await page.waitForTimeout(750);
  };
  try {
    await visit('/');
    await page.locator('#project-chart').scrollIntoViewIfNeeded();
    await page.waitForFunction(() => document.querySelector('#project-chart')?.getAttribute('aria-label')?.includes('项目状态分布'));
    await page.screenshot({path: path.join(screenshots, 'home.png'), fullPage: true});
    await visit('/?r=site/members');
    await visit('/?r=site/projects');
    await visit('/?r=site/project&id=1');
    await visit('/?r=site/login');
    await page.locator('[name="LoginForm[username]"]').fill('admin');
    await page.locator('[name="LoginForm[password]"]').fill('admin123456');
    await page.getByRole('button', {name: '登录工作台'}).click();
    await page.waitForURL('**/admin/');
    await page.waitForTimeout(1000);
    await page.screenshot({path: path.join(screenshots, 'admin.png'), fullPage: true});
    await visit('/admin/?r=site/profile');
    await page.getByRole('button', {name: '保存更改'}).click();
    await page.waitForLoadState('networkidle');
    await visit('/admin/?r=site/update');
    await page.locator('[name="RecordForm[title]"]').fill(marker);
    await page.locator('[name="RecordForm[body]"]').fill('通过真实浏览器发布动态，展示从编辑到公开页面的完整过程。');
    await page.locator('[name="RecordForm[project_id]"]').selectOption('1');
    await page.locator('input[type="checkbox"][name="RecordForm[published]"]').check();
    await page.getByRole('button', {name: '保存更改'}).click();
    await page.waitForLoadState('networkidle');
    await visit('/?r=site/updates');
    assert(await page.getByText(marker, {exact: true}).count());
    await visit('/admin/');
    await page.getByRole('button', {name: '退出登录'}).click();
    await page.waitForLoadState('networkidle');
    await visit('/?r=site/message');
    await page.locator('[name="MessageForm[name]"]').fill('演示访客');
    await page.locator('[name="MessageForm[email]"]').fill('recording@example.test');
    await page.locator('[name="MessageForm[body]"]').fill(marker + '：希望了解数据库设计。');
    await page.getByRole('button', {name: '发送留言'}).click();
    await page.waitForLoadState('networkidle');
    assert.equal(await page.getByText(marker + '：希望了解数据库设计。', {exact: true}).count(), 0, 'Pending message must not be public');
    await visit('/?r=site/login');
    await page.locator('[name="LoginForm[username]"]').fill('admin');
    await page.locator('[name="LoginForm[password]"]').fill('admin123456');
    await page.getByRole('button', {name: '登录工作台'}).click();
    await page.waitForURL('**/admin/');
    await visit('/admin/?r=site/messages');
    const card = page.locator('.moderation-card').filter({hasText: marker});
    await card.locator('select[name="status"]').selectOption('approved');
    await card.locator('textarea[name="reply"]').fill('感谢关注，数据库设计文档已在课程档案中提供。');
    await card.getByRole('button', {name: '保存审核 / 回复'}).click();
    await page.waitForLoadState('networkidle');
    await visit('/?r=site/message');
    assert(await page.getByText(marker + '：希望了解数据库设计。', {exact: true}).count());
    await visit('/?r=site/submissions');
    const downloading = page.waitForEvent('download');
    await page.getByRole('link', {name: '下载文档'}).nth(1).click();
    const download = await downloading;
    assert(download.suggestedFilename().includes('设计文档'));
    await download.saveAs(path.join(evidence, 'downloaded-design.pdf'));
    await page.waitForTimeout(1000);
    await page.setViewportSize({width: 390, height: 844});
    await visit('/');
    await page.screenshot({path: path.join(screenshots, 'mobile.png'), fullPage: true});
    assert.deepEqual(errors, [], 'Browser console must be clean');
    const version = browser.version();
    const video = page.video();
    await context.close();
    await video.saveAs(output);
    const report = {base, marker, browser: version, video: path.relative(root, output), consoleErrors: errors,
      journeys: ['public pages and live chart', 'login', 'profile save', 'publish update',
        'pending visitor message', 'moderate and reply', 'document download', 'mobile overflow']};
    fs.writeFileSync(path.join(evidence, 'report.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report, null, 2));
  } finally {
    await browser.close();
  }
}
main().catch(error => {console.error(error); process.exitCode = 1;});
