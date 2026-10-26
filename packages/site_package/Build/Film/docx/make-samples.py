#!/usr/bin/env python3
"""
Writes the Word documents the DOCX Editor film opens, into
public/fileadmin/example-studio/ (fileadmin is not in git; run this again on
a fresh lab before capturing):

    python3 packages/site_package/Build/Film/docx/make-samples.py

- website-relaunch-brief.docx: two pages with Word's own furniture: running
  header, footer with page numbers, a photo, lists, a shaded table, a comment
  and a tracked insertion.
- press-release-autumn-2026.docx and supplier-agreement-draft.docx: short
  documents, so the folder looks like one people work in.

Example Studio GmbH is the Desiderio demo company (docs/content/style-guide.md).
Needs python-docx.
"""

from pathlib import Path

import docx
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml import parse_xml
from docx.oxml.ns import nsdecls
from docx.shared import Cm, Pt, RGBColor

LAB = Path(__file__).resolve().parents[5]
TARGET = LAB / 'public/fileadmin/example-studio'
PHOTO = LAB / 'public/fileadmin/desiderio-styleguide/customer-workshop.jpg'
INK = RGBColor(0x1F, 0x2A, 0x44)
ACCENT = 'DCE6F2'


def add_field(paragraph, instruction: str, cached: str) -> None:
    for xml in (
        f'<w:r {nsdecls("w")}><w:fldChar w:fldCharType="begin"/></w:r>',
        f'<w:r {nsdecls("w")}><w:instrText xml:space="preserve"> {instruction} </w:instrText></w:r>',
        f'<w:r {nsdecls("w")}><w:fldChar w:fldCharType="separate"/></w:r>',
        f'<w:r {nsdecls("w")}><w:t>{cached}</w:t></w:r>',
        f'<w:r {nsdecls("w")}><w:fldChar w:fldCharType="end"/></w:r>',
    ):
        paragraph._p.append(parse_xml(xml))


def add_insertion(paragraph, text: str, author: str) -> None:
    paragraph._p.append(parse_xml(
        f'<w:ins {nsdecls("w")} w:id="90" w:author="{author}" w:date="2026-10-20T09:30:00Z">'
        f'<w:r><w:t xml:space="preserve">{text}</w:t></w:r></w:ins>'
    ))


def shade(cell, fill: str) -> None:
    cell._tc.get_or_add_tcPr().append(parse_xml(f'<w:shd {nsdecls("w")} w:val="clear" w:color="auto" w:fill="{fill}"/>'))


def table(document, rows: list[tuple[str, ...]], widths: list[float]) -> None:
    grid = document.add_table(rows=len(rows), cols=len(rows[0]))
    grid.style = 'Table Grid'
    grid.alignment = WD_TABLE_ALIGNMENT.CENTER
    grid.rows[0]._tr.get_or_add_trPr().append(parse_xml(f'<w:tblHeader {nsdecls("w")}/>'))
    for r, row in enumerate(rows):
        for c, value in enumerate(row):
            cell = grid.cell(r, c)
            cell.width = Cm(widths[c])
            cell.text = value
            if r == 0:
                shade(cell, ACCENT)
                cell.paragraphs[0].runs[0].bold = True


def letterhead(document, right: str) -> None:
    section = document.sections[0]
    section.top_margin = section.bottom_margin = Cm(2.2)
    section.left_margin = section.right_margin = Cm(2.4)
    head = section.header.paragraphs[0]
    head.text = 'Example Studio GmbH\t\t' + right
    head.runs[0].font.size = Pt(9)
    head.runs[0].font.color.rgb = INK
    foot = section.footer.paragraphs[0]
    foot.text = 'Page '
    add_field(foot, 'PAGE', '1')
    foot.add_run(' of ')
    add_field(foot, 'NUMPAGES', '2')
    for run in foot.runs:
        run.font.size = Pt(9)


def brief() -> None:
    document = docx.Document()
    document.core_properties.title = 'Website relaunch 2027: project brief'
    document.core_properties.author = 'Example Studio GmbH'
    letterhead(document, 'Project brief · Internal')

    document.add_paragraph('Website relaunch 2027', style='Title')
    lead = document.add_paragraph('Project brief for the new example-studio.example.com, prepared for the kick-off on 4 November 2026.')
    lead.runs[0].font.size = Pt(13)
    lead.runs[0].font.color.rgb = INK
    document.add_picture(str(PHOTO), width=Cm(16.2))
    caption = document.add_paragraph('The content team at the September workshop.')
    caption.runs[0].italic = True
    caption.runs[0].font.size = Pt(9)

    document.add_heading('Goals', level=1)
    goals = document.add_paragraph('The current site has grown to 340 pages in eleven years. Editors spend more time finding content than writing it, and half the pages have not been touched since 2022.')
    document.add_comment(goals.runs, text='Numbers from the content audit, 12 October.', author='Mara Lindqvist', initials='ML')
    for item in (
        'Cut the page count to fewer than 150, with every page owned by a named editor.',
        'Move to TYPO3 v14 and the Desiderio content elements.',
        'Publish in English and German from the first day.',
    ):
        document.add_paragraph(item, style='List Bullet')

    document.add_heading('Timeline', level=1)
    table(document, [
        ('Phase', 'Weeks', 'Owner'),
        ('Content audit and page map', '1–3', 'Mara Lindqvist'),
        ('Design system and theme preset', '3–6', 'Jonas Brandt'),
        ('Content migration', '6–10', 'Content team'),
        ('Review, training and launch', '10–12', 'Mara Lindqvist'),
    ], [7.6, 2.6, 5.4])

    document.add_heading('Budget', level=1)
    document.paragraphs[-1].paragraph_format.page_break_before = True
    table(document, [
        ('Item', 'Days', 'Amount'),
        ('Concept and page map', '6', '€ 5,400'),
        ('Theme and templates', '10', '€ 9,000'),
        ('Migration support', '8', '€ 7,200'),
        ('Total', '24', '€ 21,600'),
    ], [9.0, 2.6, 4.0])

    document.add_heading('Next steps', level=1)
    for item in (
        'Confirm the page owners by 28 October.',
        'Send the brand guidelines to the design team.',
        'Book the training day for the editors.',
    ):
        document.add_paragraph(item, style='List Number')
    closing = document.add_paragraph('Questions go to Mara Lindqvist, who leads the project.')
    add_insertion(closing, ' The steering group meets every second Thursday.', 'Jonas Brandt')

    document.save(TARGET / 'website-relaunch-brief.docx')


def press_release() -> None:
    document = docx.Document()
    document.core_properties.title = 'Press release: autumn 2026'
    letterhead(document, 'Press release')
    document.add_paragraph('Example Studio opens a second office in Hamburg', style='Title')
    document.add_paragraph('Berlin, 20 October 2026. Example Studio GmbH opens an office in Hamburg on 1 December. Twelve people will work there from the first week.')
    document.add_heading('About Example Studio', level=2)
    document.add_paragraph('Example Studio builds websites for public institutions and mid-sized companies.')
    document.save(TARGET / 'press-release-autumn-2026.docx')


def agreement() -> None:
    document = docx.Document()
    document.core_properties.title = 'Supplier agreement (draft)'
    letterhead(document, 'Draft · Not for signature')
    document.add_paragraph('Supplier agreement', style='Title')
    for number, (title, text) in enumerate((
        ('Subject', 'The supplier provides hosting and maintenance for the website.'),
        ('Term', 'The agreement runs for 24 months from the date of signature.'),
        ('Fees', 'Fees are invoiced quarterly in advance.'),
    ), start=1):
        document.add_heading(f'{number}. {title}', level=2)
        document.add_paragraph(text)
    document.save(TARGET / 'supplier-agreement-draft.docx')


def handbook() -> None:
    """Three Heading 1 sections: the import makes one page of each."""
    document = docx.Document()
    document.core_properties.title = 'Team handbook'
    letterhead(document, 'Team handbook')
    document.add_heading('Welcome to Example Studio', level=1)
    document.add_paragraph('This handbook is for everyone who joins the team. It explains how we plan, write and publish, and who to ask when something is unclear.')
    document.add_picture(str(PHOTO), width=Cm(14))
    document.add_paragraph('We are 28 people in Berlin and, from December, Hamburg.')

    document.add_heading('How we work', level=1)
    document.add_paragraph('Every project has one owner and a weekly review. Decisions go into the project brief, so nobody has to search chat history.')
    for item in ('Plan on Monday.', 'Review on Thursday.', 'Publish when the owner agrees.'):
        document.add_paragraph(item, style='List Bullet')
    document.add_paragraph('Write it down once, where everyone can find it.', style='Quote')

    document.add_heading('Tools we use', level=1)
    document.add_paragraph('These are the tools every new colleague gets on the first day.')
    table(document, [
        ('Tool', 'What for'),
        ('TYPO3', 'The website and all its pages'),
        ('Word', 'Briefs, contracts and press releases'),
        ('Calendar', 'Reviews and training days'),
    ], [5.0, 10.6])
    document.save(TARGET / 'team-handbook.docx')


if __name__ == '__main__':
    TARGET.mkdir(parents=True, exist_ok=True)
    brief()
    press_release()
    agreement()
    handbook()
    print(f'wrote 4 documents to {TARGET}')
