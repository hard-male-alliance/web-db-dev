"""Build the six course PDFs from editable UTF-8 sources and the real SQL schema.

Requires ReportLab. A local TrueType Chinese font can be selected with --font;
otherwise common system fonts are tried before ReportLab's standard CJK font.
The generated dictionary is derived from data/schema.sql, not a second
hand-maintained schema. Run from any working directory.
"""

import argparse
from html import escape
from pathlib import Path
import re

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.cidfonts import UnicodeCIDFont
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak,
    Flowable, Image,
)

ROOT = Path(__file__).resolve().parents[1]
TEAM = "星河工坊"
IDS = "DEMO01_DEMO02_DEMO03"
TITLES = ["需求文档", "设计文档", "实现文档", "用户手册", "部署文档", "项目展示"]
INK = colors.HexColor("#17324d")
BLUE = colors.HexColor("#167b9b")
FONT = "Chinese"


class Diagram(Flowable):
    """Compact vector diagrams with explicit direction and generous line spacing."""

    def __init__(self, rows, width, slide=False):
        Flowable.__init__(self)
        self.rows, self.width = rows, width
        self.step, self.font_size = (67, 13) if slide else (40, 9)
        self.height = len(rows) * self.step + 12

    def draw(self):
        c = self.canv
        for i, row in enumerate(self.rows):
            boxes = row.split(" -> ")
            gap = 20
            w = (self.width - gap * (len(boxes) - 1)) / len(boxes)
            y = self.height - (i + 1) * self.step
            for j, label in enumerate(boxes):
                x = j * (w + gap)
                c.setFillColor(colors.HexColor("#eef6fa"))
                c.setStrokeColor(BLUE)
                height = self.step - 11
                mid = height / 2
                c.roundRect(x, y, w, height, 5, fill=1)
                c.setFillColor(INK)
                c.setFont(FONT, self.font_size)
                c.drawCentredString(x + w / 2, y + mid - self.font_size / 3, label)
                if j < len(boxes) - 1:
                    c.line(x + w + 2, y + mid, x + w + gap - 2, y + mid)
                    c.line(x + w + gap - 6, y + mid + 3, x + w + gap - 2, y + mid)
                    c.line(x + w + gap - 6, y + mid - 3, x + w + gap - 2, y + mid)


def register_font(path):
    global FONT
    candidates = [path] if path else [
        "C:/Windows/Fonts/simhei.ttf",
        "/usr/share/fonts/truetype/arphic/uming.ttc",
        "/System/Library/Fonts/STHeiti Light.ttc",
    ]
    for font in candidates:
        if font and Path(font).is_file():
            pdfmetrics.registerFont(TTFont("Chinese", font))
            FONT = "Chinese"
            return
    pdfmetrics.registerFont(UnicodeCIDFont("STSong-Light"))
    FONT = "STSong-Light"


def styles(slide=False):
    base = getSampleStyleSheet()
    for name, size, leading in [("Body", 9.5, 14), ("Small", 8, 12),
                                ("TitleCN", 26, 36), ("H1CN", 17, 25), ("H2CN", 12, 19)]:
        if slide:
            size, leading = {"Body": (16, 25), "Small": (13, 20), "TitleCN": (38, 52),
                             "H1CN": (27, 38), "H2CN": (20, 28)}[name]
        base.add(ParagraphStyle(name, fontName=FONT, fontSize=size,
                               leading=leading, textColor=INK, wordWrap="CJK",
                               spaceAfter=14 if slide else 6,
                               keepWithNext=name in {"H1CN", "H2CN"},
                               alignment=TA_CENTER if name == "TitleCN" else 0))
    return base


def paragraph(text, style):
    return Paragraph(escape(text).replace("`", ""), style)


def make_table(rows, width, s):
    n = len(rows[0])
    data = [[paragraph(cell, s["Small"]) for cell in row] for row in rows]
    widths = [width / n] * n
    if n == 3:
        widths = [width * .22, width * .28, width * .50]
    t = Table(data, colWidths=widths, repeatRows=1, hAlign="LEFT")
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#dcecf5")),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("GRID", (0, 0), (-1, -1), .35, colors.HexColor("#cad9e1")),
        ("LEFTPADDING", (0, 0), (-1, -1), 7), ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 6), ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
    ]))
    return t


def dictionary(width, s):
    sql = (ROOT / "data/schema.sql").read_text(encoding="utf-8")
    blocks = re.findall(r"CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?\s*\((.*?)\)\s*ENGINE", sql, re.S | re.I)
    if len(blocks) < 11:
        raise ValueError("Dictionary requires at least eleven CREATE TABLE ... ENGINE blocks")
    story = [paragraph(f"数据库字典：{len(blocks)} 张业务表", s["H1CN"]),
             paragraph("以下逐字段定义直接读取 data/schema.sql，data/install.sql 为同结构的实际数据库导出。NULL、默认值、唯一键及外键约束以同表的 SQL 约束行完整列出；类型中的长度不是字符显示宽度。主键（Primary Key）标识实体，外键（Foreign Key）约束引用完整性。", s["Body"])]
    for name, body in blocks:
        story.append(paragraph(name, s["H2CN"]))
        rows = [["字段", "类型", "约束 / 默认值"]]
        constraints = []
        for line in body.splitlines():
            line = line.strip().rstrip(",")
            match = re.match(r"`?(\w+)`?\s+(\w+(?:\([^)]*\))?(?: UNSIGNED)?)\s*(.*)", line, re.I)
            if match and match.group(1).upper() in {"PRIMARY", "CONSTRAINT", "UNIQUE", "INDEX", "KEY", "FOREIGN", "CHECK"}:
                match = None
            if match:
                rows.append(list(match.groups()))
            elif line:
                constraints.append(line)
        if len(rows) == 1:
            raise ValueError(f"No parsed columns in {name}")
        story.append(make_table(rows, width, s))
        for constraint in constraints:
            story.append(paragraph(constraint, s["Small"]))
        story.append(Spacer(1, 5 * mm))
    return story


def parse_source(source, width, s, slide=False):
    lines = source.splitlines()
    story, i = [], 0
    while i < len(lines):
        line = lines[i].strip()
        if not line:
            i += 1
            continue
        if line == "@page":
            story.append(PageBreak())
        elif line == "@dictionary":
            story.extend(dictionary(width, s))
        elif line.startswith("@image "):
            path = (ROOT / line[7:]).resolve()
            if not path.is_relative_to(ROOT) or not path.is_file():
                raise ValueError(f"Image must be an existing project file: {line[7:]}")
            iw, ih = ImageReader(str(path)).getSize()
            scale = min(width / iw, (285 if slide else 390) / ih)
            story.append(Image(str(path), width=iw * scale, height=ih * scale))
            story.append(Spacer(1, 4 * mm))
        elif line.startswith("@diagram"):
            rows = []
            i += 1
            while i < len(lines) and lines[i].strip() != "@end":
                rows.append(lines[i].strip())
                i += 1
            story.append(Diagram(rows, width, slide))
        elif line.startswith("|"):
            rows = []
            while i < len(lines) and lines[i].strip().startswith("|"):
                row = [x.strip() for x in lines[i].strip().strip("|").split("|")]
                if not all(re.match(r"^[-: ]+$", cell) for cell in row):
                    rows.append(row)
                i += 1
            story.append(make_table(rows, width, s))
            story.append(Spacer(1, 4 * mm))
            continue
        elif line.startswith("# "):
            story.append(paragraph(line[2:], s["TitleCN"]))
        elif line.startswith("## "):
            story.append(paragraph(line[3:], s["H1CN"]))
        elif line.startswith("### "):
            story.append(paragraph(line[4:], s["H2CN"]))
        elif line.startswith("参考："):
            story.append(paragraph(line, s["Small"]))
        else:
            story.append(paragraph(line, s["Body"]))
        i += 1
    return story


def footer(canvas, doc):
    canvas.setStrokeColor(colors.HexColor("#cedce6"))
    canvas.line(doc.leftMargin, 15 * mm, doc.pagesize[0] - doc.rightMargin, 15 * mm)
    canvas.setFont(FONT, 8)
    canvas.setFillColor(INK)
    canvas.drawString(doc.leftMargin, 10 * mm, "星河工坊 / 互联网数据库开发 / 演示成员编号须替换")
    canvas.drawRightString(doc.pagesize[0] - doc.rightMargin, 10 * mm, str(doc.page))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--font", help="Path to a TrueType font with Simplified Chinese glyphs")
    args = parser.parse_args()
    register_font(args.font)
    out = ROOT / "data/team"
    out.mkdir(parents=True, exist_ok=True)
    for index, title in enumerate(TITLES, 1):
        s = styles(slide=index == 6)
        source = ROOT / f"docs/course/{index:02d}-{title}.md"
        target = out / f"{TEAM}_{title}({IDS}).pdf"
        doc = SimpleDocTemplate(str(target), pagesize=landscape(A4) if index == 6 else A4,
                                rightMargin=19 * mm, leftMargin=19 * mm,
                                topMargin=19 * mm, bottomMargin=23 * mm,
                                title=f"{TEAM} - {title}", author="星河工坊（演示团队）")
        doc.build(parse_source(source.read_text(encoding="utf-8"), doc.width, s, slide=index == 6),
                  onFirstPage=footer, onLaterPages=footer)
        print(target.relative_to(ROOT))


if __name__ == "__main__":
    main()
