from pathlib import Path
from zipfile import ZipFile

from PIL import Image, ImageDraw, ImageFont
from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.style import WD_STYLE_TYPE
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Inches, Pt, RGBColor


ROOT = Path(__file__).resolve().parent
PROJECT = ROOT / "TwoActivities"
REFERENCE = Path(r"D:\STIMIK_WICIDA\Praktikum PPS\2443908_Syahid Hussein_Labsheet 5 PPS.docx")
OUTPUT = ROOT / "2443908_Syahid Hussein_Labsheet 9-10 PPS.docx"
ASSETS = ROOT / "report-assets"
ASSETS.mkdir(exist_ok=True)


def set_cell_shading(cell, fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:fill"), fill)
    tc_pr.append(shd)


def set_cell_border(cell, color="D5D9E2", size="8"):
    tc_pr = cell._tc.get_or_add_tcPr()
    borders = tc_pr.first_child_found_in("w:tcBorders")
    if borders is None:
        borders = OxmlElement("w:tcBorders")
        tc_pr.append(borders)
    for edge in ("top", "left", "bottom", "right"):
        tag = "w:" + edge
        el = borders.find(qn(tag))
        if el is None:
            el = OxmlElement(tag)
            borders.append(el)
        el.set(qn("w:val"), "single")
        el.set(qn("w:sz"), size)
        el.set(qn("w:color"), color)


def add_page_number(section):
    footer = section.footer
    p = footer.paragraphs[0]
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run()
    fld_begin = OxmlElement("w:fldChar")
    fld_begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = " PAGE "
    fld_end = OxmlElement("w:fldChar")
    fld_end.set(qn("w:fldCharType"), "end")
    run._r.extend([fld_begin, instr, fld_end])


def add_toc(doc):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    run = p.add_run()
    begin = OxmlElement("w:fldChar")
    begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = 'TOC \\o "1-3" \\h \\z \\u'
    separate = OxmlElement("w:fldChar")
    separate.set(qn("w:fldCharType"), "separate")
    text = OxmlElement("w:t")
    text.text = "Daftar isi akan diperbarui otomatis."
    separate.append(text)
    end = OxmlElement("w:fldChar")
    end.set(qn("w:fldCharType"), "end")
    run._r.extend([begin, instr, separate, end])


def keep_with_next(paragraph):
    paragraph.paragraph_format.keep_with_next = True


def add_body(doc, text, indent=True, space_after=Pt(4)):
    p = doc.add_paragraph(style="Body Text")
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.space_after = space_after
    if indent:
        p.paragraph_format.first_line_indent = Cm(1.25)
    p.add_run(text)
    return p


def add_bullet(doc, text):
    p = doc.add_paragraph(style="List Bullet")
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.left_indent = Cm(1.0)
    p.paragraph_format.first_line_indent = Cm(-0.45)
    p.paragraph_format.space_after = Pt(4)
    p.add_run(text)
    return p


def add_code(doc, code, caption=None):
    table = doc.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    cell = table.cell(0, 0)
    cell.width = Inches(6.25)
    cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
    set_cell_shading(cell, "F4F6FA")
    set_cell_border(cell)
    p = cell.paragraphs[0]
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after = Pt(4)
    p.paragraph_format.line_spacing = 1.0
    run = p.add_run(code.rstrip())
    run.font.name = "Consolas"
    run._element.rPr.rFonts.set(qn("w:eastAsia"), "Consolas")
    run.font.size = Pt(8.5)
    run.font.color.rgb = RGBColor(31, 41, 55)
    if caption:
        cp = doc.add_paragraph(caption, style="Caption")
        cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    return table


def add_heading(doc, text, level=1):
    p = doc.add_heading(text, level=level)
    keep_with_next(p)
    return p


def extract_logo():
    target = ASSETS / "logo.png"
    with ZipFile(REFERENCE) as zf:
        target.write_bytes(zf.read("word/media/image1.png"))
    return target


def font(size, bold=False):
    candidates = [
        r"C:\Windows\Fonts\times.ttf",
        r"C:\Windows\Fonts\timesbd.ttf" if bold else r"C:\Windows\Fonts\times.ttf",
        r"C:\Windows\Fonts\arial.ttf",
    ]
    for candidate in candidates:
        if Path(candidate).exists():
            return ImageFont.truetype(candidate, size)
    return ImageFont.load_default()


def rounded(draw, box, radius, fill, outline=None, width=1):
    draw.rounded_rectangle(box, radius=radius, fill=fill, outline=outline, width=width)


def create_ui_preview():
    out = ASSETS / "ui-preview.png"
    img = Image.new("RGB", (1500, 800), "white")
    d = ImageDraw.Draw(img)
    title_f = font(40, True)
    body_f = font(24)
    small_f = font(19)
    d.text((70, 32), "Pratinjau desain berdasarkan layout XML", fill="#172554", font=title_f)

    def phone(x, screen_title, second=False):
        rounded(d, (x, 105, x + 520, 755), 38, "#111827")
        rounded(d, (x + 18, 124, x + 502, 735), 28, "#F8FAFC")
        d.rectangle((x + 18, 124, x + 502, 188), fill="#173B69")
        d.text((x + 48, 141), screen_title, fill="white", font=body_f)
        if not second:
            d.text((x + 55, 235), "EXPLORE THE LESSON", fill="#C98A23", font=small_f)
            d.text((x + 55, 280), "Two Activities", fill="#173B69", font=font(38, True))
            d.text((x + 55, 335), "Choose one section to continue.", fill="#475569", font=small_f)
            labels = ["TEXT ONE", "TEXT TWO", "TEXT THREE"]
            for i, label in enumerate(labels):
                y = 410 + i * 82
                rounded(d, (x + 55, y, x + 465, y + 60), 12, "#173B69")
                tw = d.textbbox((0, 0), label, font=body_f)[2]
                d.text((x + 260 - tw / 2, y + 15), label, fill="white", font=body_f)
        else:
            d.text((x + 55, 228), "SECTION ONE", fill="#C98A23", font=small_f)
            d.text((x + 55, 273), "Activity Lifecycle", fill="#173B69", font=font(35, True))
            rounded(d, (x + 55, 340, x + 465, 665), 16, "#FFFFFF", "#D7DEE9", 3)
            lines = [
                "An Activity moves through lifecycle", "callbacks such as onCreate(),", "onStart(), onResume(), onPause(),", "onStop(), and onDestroy().", "", "The ScrollView allows longer text", "to remain accessible on small screens."
            ]
            y = 375
            for line in lines:
                d.text((x + 82, y), line, fill="#334155", font=small_f)
                y += 37

    phone(115, "Two Activities", False)
    phone(865, "Reading", True)
    d.line((650, 430, 820, 430), fill="#C98A23", width=8)
    d.polygon([(820, 430), (785, 410), (785, 450)], fill="#C98A23")
    img.save(out, quality=95)
    return out


def create_flow_diagram():
    out = ASSETS / "intent-flow.png"
    img = Image.new("RGB", (1500, 470), "white")
    d = ImageDraw.Draw(img)
    heading = font(35, True)
    label = font(23, True)
    small = font(20)
    d.text((65, 35), "Alur perpindahan activity dan data intent extra", fill="#172554", font=heading)
    boxes = [
        (65, 150, 370, 350, "MainActivity", "Tiga tombol\nmemanggil openText()"),
        (595, 150, 900, 350, "Intent", "putExtra(TEXT_ID,\n1 / 2 / 3)"),
        (1125, 150, 1430, 350, "SecondActivity", "Membaca extra dan\nmenampilkan bagian teks"),
    ]
    for x1, y1, x2, y2, title, body in boxes:
        rounded(d, (x1, y1, x2, y2), 18, "#F8FAFC", "#173B69", 4)
        tw = d.textbbox((0, 0), title, font=label)[2]
        d.text(((x1 + x2 - tw) / 2, y1 + 28), title, fill="#173B69", font=label)
        y = y1 + 92
        for line in body.split("\n"):
            w = d.textbbox((0, 0), line, font=small)[2]
            d.text(((x1 + x2 - w) / 2, y), line, fill="#475569", font=small)
            y += 39
    for x1, x2 in [(390, 575), (920, 1105)]:
        d.line((x1, 250, x2, 250), fill="#C98A23", width=7)
        d.polygon([(x2, 250), (x2 - 28, 232), (x2 - 28, 268)], fill="#C98A23")
    img.save(out, quality=95)
    return out


def configure_styles(doc):
    normal = doc.styles["Normal"]
    normal.font.name = "Times New Roman"
    normal._element.rPr.rFonts.set(qn("w:eastAsia"), "Times New Roman")
    normal.font.size = Pt(12)

    body = doc.styles["Body Text"]
    body.font.name = "Times New Roman"
    body._element.rPr.rFonts.set(qn("w:eastAsia"), "Times New Roman")
    body.font.size = Pt(12)
    body.paragraph_format.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
    body.paragraph_format.space_after = Pt(4)

    for level, size in [(1, 16), (2, 12), (3, 12)]:
        style = doc.styles[f"Heading {level}"]
        style.font.name = "Times New Roman"
        style._element.rPr.rFonts.set(qn("w:eastAsia"), "Times New Roman")
        style.font.size = Pt(size)
        style.font.bold = True
        style.font.color.rgb = RGBColor(0, 0, 0)
        style.paragraph_format.space_before = Pt(10 if level == 1 else 6)
        style.paragraph_format.space_after = Pt(6)
        style.paragraph_format.keep_with_next = True

    caption = doc.styles["Caption"]
    caption.font.name = "Times New Roman"
    caption._element.rPr.rFonts.set(qn("w:eastAsia"), "Times New Roman")
    caption.font.size = Pt(10)
    caption.font.italic = True
    caption.font.color.rgb = RGBColor(0, 0, 0)

    list_style = doc.styles["List Bullet"]
    list_style.font.name = "Times New Roman"
    list_style._element.rPr.rFonts.set(qn("w:eastAsia"), "Times New Roman")
    list_style.font.size = Pt(12)
    list_style.paragraph_format.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE


def main():
    logo = extract_logo()
    preview = create_ui_preview()
    flow = create_flow_diagram()

    main_java = (PROJECT / "app/src/main/java/com/example/twoactivities/MainActivity.java").read_text(encoding="utf-8")
    second_java = (PROJECT / "app/src/main/java/com/example/twoactivities/SecondActivity.java").read_text(encoding="utf-8")
    manifest = (PROJECT / "app/src/main/AndroidManifest.xml").read_text(encoding="utf-8")
    main_xml = (PROJECT / "app/src/main/res/layout/activity_main.xml").read_text(encoding="utf-8")
    second_xml = (PROJECT / "app/src/main/res/layout/activity_second.xml").read_text(encoding="utf-8")

    doc = Document()
    configure_styles(doc)
    cover = doc.sections[0]
    cover.page_width = Inches(8.5)
    cover.page_height = Inches(11)
    cover.left_margin = Inches(0.97)
    cover.right_margin = Inches(1.18)
    cover.top_margin = Inches(1.22)
    cover.bottom_margin = Inches(0.9)
    cover.different_first_page_header_footer = True

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_after = Pt(10)
    r = p.add_run("LAPORAN AKHIR PRAKTIKUM")
    r.bold = True
    r.font.name = "Times New Roman"
    r.font.size = Pt(15)
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_after = Pt(14)
    r = p.add_run("PEMROGRAMAN PERANGKAT SELULER\nPERTEMUAN KE- LABSHEET 9-10 (PPS)")
    r.bold = True
    r.font.name = "Times New Roman"
    r.font.size = Pt(15)

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_after = Pt(8)
    p.add_run().add_picture(str(logo), width=Inches(2.55))

    identity = doc.add_table(rows=3, cols=3)
    identity.alignment = WD_TABLE_ALIGNMENT.CENTER
    identity.autofit = False
    widths = [Inches(1.5), Inches(0.25), Inches(2.2)]
    rows = [("Nim", ":", "2443908"), ("Nama Mahasiswa", ":", "Syahid Hussein"), ("Kelas/ Kelompok", ":", "TI/MA/1")]
    for row, values in zip(identity.rows, rows):
        for i, (cell, value) in enumerate(zip(row.cells, values)):
            cell.width = widths[i]
            cell.text = value
            for pp in cell.paragraphs:
                pp.paragraph_format.space_after = Pt(0)
                for rr in pp.runs:
                    rr.font.name = "Times New Roman"
                    rr.font.size = Pt(12)
            tc_pr = cell._tc.get_or_add_tcPr()
            borders = OxmlElement("w:tcBorders")
            for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
                el = OxmlElement("w:" + edge)
                el.set(qn("w:val"), "nil")
                borders.append(el)
            tc_pr.append(borders)

    for _ in range(2):
        doc.add_paragraph()
    for text in ["PROGRAM STUDI TEKNIK INFORMATIKA", "STMIK WIDYA CIPTA DHARMA SAMARINDA", "2026"]:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_after = Pt(8)
        r = p.add_run(text)
        r.font.name = "Times New Roman"
        r.font.size = Pt(13)

    body_section = doc.add_section(WD_SECTION.NEW_PAGE)
    body_section.page_width = Inches(8.5)
    body_section.page_height = Inches(11)
    body_section.left_margin = Inches(1)
    body_section.right_margin = Inches(1)
    body_section.top_margin = Inches(0.85)
    body_section.bottom_margin = Inches(0.8)
    add_page_number(body_section)

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_after = Pt(18)
    r = p.add_run("DAFTAR ISI")
    r.bold = True
    r.font.size = Pt(16)
    add_toc(doc)

    doc.add_page_break()
    add_heading(doc, "1. Dasar Teori", 1)
    add_body(doc, "Activity merupakan salah satu komponen utama aplikasi Android yang merepresentasikan satu layar tempat pengguna berinteraksi. Setiap activity dikelola oleh sistem Android melalui serangkaian callback. Memahami siklus hidup activity penting agar aplikasi dapat mengalokasikan sumber daya secara tepat, mempertahankan data pengguna, dan tetap stabil ketika konfigurasi perangkat berubah.")
    add_heading(doc, "1.1 Siklus Hidup Activity", 2)
    add_body(doc, "Siklus hidup activity dimulai ketika instance dibuat dan berakhir ketika instance dihancurkan. Sistem memanggil callback sesuai perubahan visibilitas dan fokus layar. Callback tersebut tidak dipanggil secara sembarang, tetapi membentuk urutan keadaan yang dapat dipakai pengembang untuk menempatkan logika aplikasi.")
    lifecycle = [
        ("onCreate()", "dipanggil saat activity pertama kali dibuat. Pada tahap ini layout dipasang melalui setContentView(), view diinisialisasi, dan state yang tersimpan dapat dipulihkan."),
        ("onStart()", "menandai activity mulai terlihat oleh pengguna, tetapi belum tentu menjadi layar yang aktif."),
        ("onResume()", "menandai activity berada di depan dan siap menerima interaksi pengguna."),
        ("onPause()", "dipanggil ketika activity kehilangan fokus sebagian. Pekerjaan singkat seperti menyimpan perubahan kecil atau menghentikan animasi dapat dilakukan di sini."),
        ("onStop()", "dipanggil ketika activity tidak lagi terlihat. Sumber daya yang tidak dibutuhkan saat layar tersembunyi sebaiknya dilepas."),
        ("onRestart()", "dipanggil setelah onStop() ketika activity akan kembali dimulai."),
        ("onDestroy()", "dipanggil sebelum activity dihancurkan akibat finish() atau keputusan sistem."),
    ]
    for name, desc in lifecycle:
        p = add_bullet(doc, "")
        p.runs[0].text = name + " "
        p.runs[0].bold = True
        p.add_run(desc)

    add_heading(doc, "1.2 Instance State", 2)
    add_body(doc, "Instance state adalah data antarmuka yang diperlukan untuk mengembalikan activity ke kondisi sebelumnya, misalnya teks yang telah diketik, posisi gulir, atau pilihan pengguna. Ketika activity dibuat ulang karena rotasi layar atau tekanan memori, data sementara dapat disimpan ke Bundle melalui onSaveInstanceState() dan dipulihkan dari savedInstanceState pada onCreate().")
    add_body(doc, "View standar umumnya menyimpan state-nya sendiri apabila memiliki ID. Data yang lebih kompleks dan perlu bertahan selama perubahan konfigurasi dapat ditempatkan dalam ViewModel, sedangkan data yang harus tetap tersedia setelah aplikasi ditutup perlu disimpan secara persisten, misalnya pada database atau DataStore. Dengan demikian, instance state bukan pengganti penyimpanan permanen.")
    add_code(doc, "@Override\nprotected void onSaveInstanceState(Bundle outState) {\n    super.onSaveInstanceState(outState);\n    outState.putInt(\"selected_text\", selectedText);\n}\n\n@Override\nprotected void onCreate(Bundle savedInstanceState) {\n    super.onCreate(savedInstanceState);\n    if (savedInstanceState != null) {\n        selectedText = savedInstanceState.getInt(\"selected_text\", 1);\n    }\n}", "Gambar 1.1 Contoh penyimpanan dan pemulihan instance state.")

    doc.add_page_break()
    add_heading(doc, "1.3 Intent Eksplisit dan Intent Extra", 2)
    add_body(doc, "Intent adalah objek pesan yang digunakan untuk meminta aksi dari komponen Android. Intent eksplisit menyebutkan kelas tujuan secara langsung sehingga cocok untuk berpindah dari MainActivity ke SecondActivity di dalam aplikasi yang sama. Data tambahan dikirim melalui pasangan key-value dengan putExtra() dan dibaca di activity tujuan dengan getIntExtra(), getStringExtra(), atau metode lain sesuai tipe data.")
    add_body(doc, "Pada aplikasi ini, satu activity tujuan dipakai untuk menampilkan tiga bagian teks. MainActivity tidak membuat tiga layar terpisah; tombol hanya mengirim nilai 1, 2, atau 3. SecondActivity membaca nilai tersebut lalu memilih label, judul, dan isi yang sesuai. Pendekatan ini mengurangi duplikasi dan menjaga navigasi tetap sederhana.")
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run().add_picture(str(flow), width=Inches(6.25))
    cp = doc.add_paragraph("Gambar 1.2 Alur intent eksplisit dan intent extra pada aplikasi.", style="Caption")
    cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    add_heading(doc, "1.4 ScrollView dan Navigasi Parent Activity", 2)
    add_body(doc, "ScrollView merupakan ViewGroup yang memungkinkan isi vertikal digulir ketika tinggi konten melebihi layar. ScrollView hanya boleh memiliki satu child langsung, sehingga sebuah LinearLayout ditempatkan di dalamnya sebagai wadah label, judul, dan isi bacaan. SecondActivity juga dideklarasikan memiliki parent MainActivity pada manifest agar tombol Up di action bar mengarahkan pengguna ke layar utama.")

    doc.add_page_break()
    add_heading(doc, "2. Prosedur", 1)
    steps = [
        "Membuat proyek Android bernama TwoActivities dengan bahasa Java dan minimum SDK 24.",
        "Menambahkan MainActivity sebagai launcher activity dan membuat SecondActivity sebagai layar tujuan.",
        "Menyusun activity_main.xml yang berisi pengantar dan tiga tombol berlabel Text One, Text Two, serta Text Three.",
        "Menghubungkan ketiga tombol ke metode openText(View) pada MainActivity melalui atribut android:onClick.",
        "Membuat Intent eksplisit menuju SecondActivity dan menyisipkan nilai pilihan menggunakan putExtra().",
        "Menyusun activity_second.xml dengan ScrollView sebagai root dan LinearLayout sebagai satu-satunya child langsung.",
        "Membaca intent extra pada SecondActivity, lalu memilih resource label, judul, dan isi berdasarkan nilai 1, 2, atau 3.",
        "Mendaftarkan kedua activity pada AndroidManifest.xml serta menentukan MainActivity sebagai parent dari SecondActivity.",
        "Memeriksa struktur proyek, konsistensi ID view, resource string, dan konfigurasi Gradle sebelum pengujian mandiri di Android Studio.",
    ]
    for i, step in enumerate(steps, 1):
        p = doc.add_paragraph(style="Body Text")
        p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        p.paragraph_format.left_indent = Cm(0.8)
        p.paragraph_format.first_line_indent = Cm(-0.8)
        p.add_run(f"{i}. ").bold = True
        p.add_run(step)

    add_heading(doc, "2.1 Struktur Berkas Utama", 2)
    structure = [
        "app/src/main/java/com/example/twoactivities/MainActivity.java",
        "app/src/main/java/com/example/twoactivities/SecondActivity.java",
        "app/src/main/res/layout/activity_main.xml",
        "app/src/main/res/layout/activity_second.xml",
        "app/src/main/res/values/strings.xml",
        "app/src/main/AndroidManifest.xml",
    ]
    for item in structure:
        add_bullet(doc, item)

    doc.add_page_break()
    add_heading(doc, "3. Data / Hasil Praktikum", 1)
    add_heading(doc, "3.1 Rancangan Tampilan Aplikasi", 2)
    add_body(doc, "Layar utama menampilkan tiga tombol pilihan. Setiap tombol menuju activity yang sama, sedangkan isi bacaan ditentukan oleh nilai extra. Layar kedua menggunakan ScrollView agar paragraf panjang tetap dapat dibaca pada perangkat dengan tinggi layar yang berbeda.")
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run().add_picture(str(preview), width=Inches(6.25))
    cp = doc.add_paragraph("Gambar 3.1 Pratinjau desain berdasarkan layout XML (bukan tangkapan layar emulator).", style="Caption")
    cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    add_heading(doc, "3.2 Implementasi MainActivity", 2)
    add_body(doc, "MainActivity menyediakan konstanta key untuk extra dan satu event handler bagi seluruh tombol. ID view menentukan angka bagian yang dikirim. Nilai default adalah 1 sehingga tombol pertama maupun kondisi tak terduga tetap menghasilkan konten yang valid.")
    compact_main = """public void openText(View view) {
    int textId;

    if (view.getId() == R.id.button_text_two) {
        textId = 2;
    } else if (view.getId() == R.id.button_text_three) {
        textId = 3;
    } else {
        textId = 1;
    }

    Intent intent = new Intent(this, SecondActivity.class);
    intent.putExtra(EXTRA_TEXT_ID, textId);
    startActivity(intent);
}"""
    add_code(doc, compact_main, "Gambar 3.2 Implementasi intent eksplisit dan intent extra pada MainActivity.")

    add_heading(doc, "3.3 Implementasi SecondActivity", 2)
    add_body(doc, "SecondActivity mengambil Intent yang memulai activity, membaca EXTRA_TEXT_ID, dan mengisi tiga TextView. Percabangan memetakan nilai 1, 2, atau 3 ke resource string yang berbeda. Action bar juga menampilkan tombol Up karena parent activity telah dikonfigurasi.")
    compact_second = "\n".join(second_java.splitlines()[11:48])
    add_code(doc, compact_second, "Gambar 3.3 Pemilihan konten pada SecondActivity.")
    add_heading(doc, "3.4 Layout ScrollView", 2)
    add_body(doc, "Root layout activity_second.xml adalah ScrollView. Di dalamnya terdapat satu LinearLayout vertikal yang menampung label bagian, judul, dan isi teks. Penggunaan fillViewport membuat konten pendek tetap dapat mengisi tinggi viewport, sedangkan konten panjang dapat digulir.")
    second_excerpt = """<ScrollView
    xmlns:android=\"http://schemas.android.com/apk/res/android\"
    android:layout_width=\"match_parent\"
    android:layout_height=\"match_parent\"
    android:fillViewport=\"true\">

    <LinearLayout
        android:layout_width=\"match_parent\"
        android:layout_height=\"wrap_content\"
        android:orientation=\"vertical\">
    </LinearLayout></ScrollView>"""
    add_code(doc, second_excerpt, "Gambar 3.4 Struktur awal ScrollView pada activity_second.xml.")

    add_heading(doc, "3.5 Deklarasi Activity pada Manifest", 2)
    add_body(doc, "MainActivity diberi intent-filter MAIN dan LAUNCHER. SecondActivity tidak diekspor keluar aplikasi dan menunjuk MainActivity sebagai parent. Konfigurasi ini mendukung navigasi internal serta mengurangi permukaan komponen yang dapat dipanggil aplikasi lain.")
    manifest_excerpt = "\n".join(manifest.splitlines()[10:35])
    add_code(doc, manifest_excerpt, "Gambar 3.5 Deklarasi MainActivity dan SecondActivity pada AndroidManifest.xml.")
    add_heading(doc, "3.6 Pemetaan Tombol dan Hasil", 2)
    table = doc.add_table(rows=1, cols=4)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.style = "Table Grid"
    headers = ["Tombol", "Nilai Extra", "Judul di SecondActivity", "Kontainer"]
    for i, text in enumerate(headers):
        cell = table.rows[0].cells[i]
        cell.text = text
        set_cell_shading(cell, "DCE6F1")
        for run in cell.paragraphs[0].runs:
            run.bold = True
    values = [
        ("Text One", "1", "Activity Lifecycle", "ScrollView"),
        ("Text Two", "2", "Explicit Intent", "ScrollView"),
        ("Text Three", "3", "Instance State", "ScrollView"),
    ]
    for values_row in values:
        cells = table.add_row().cells
        for i, value in enumerate(values_row):
            cells[i].text = value
    for row in table.rows:
        for cell in row.cells:
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
            for pp in cell.paragraphs:
                pp.alignment = WD_ALIGN_PARAGRAPH.CENTER
                for rr in pp.runs:
                    rr.font.name = "Times New Roman"
                    rr.font.size = Pt(10.5)
    cp = doc.add_paragraph("Tabel 3.1 Pemetaan tombol, intent extra, dan konten tujuan.", style="Caption")
    cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    add_body(doc, "Berdasarkan pemeriksaan statis, setiap tombol memiliki ID yang sesuai dengan percabangan openText(), key extra konsisten pada kedua activity, dan semua resource yang digunakan telah tersedia. Eksekusi pada emulator atau perangkat dilakukan secara mandiri oleh praktikan sesuai instruksi pengguna.", indent=False)

    doc.add_page_break()
    add_heading(doc, "4. Analisis dan Pembahasan", 1)
    add_heading(doc, "4.1 Efisiensi Penggunaan Satu Activity Tujuan", 2)
    add_body(doc, "Tiga pilihan konten tidak memerlukan tiga activity tujuan. Dengan satu SecondActivity dan extra bertipe integer, struktur aplikasi lebih ringkas. Konten baru cukup menambah resource dan pemetaan tanpa menggandakan layout maupun navigasi.")
    add_heading(doc, "4.2 Ketepatan Intent Extra", 2)
    add_body(doc, "Key extra didefinisikan sebagai konstanta pada MainActivity agar pengirim dan penerima memakai nama yang sama. getIntExtra() menyediakan default 1 sehingga SecondActivity tetap menampilkan konten valid ketika extra tidak tersedia.")
    add_heading(doc, "4.3 Responsivitas Konten", 2)
    add_body(doc, "ScrollView menangani isi yang lebih tinggi daripada layar. Ukuran match_parent pada root dan wrap_content pada child mendukung beragam tinggi perangkat, sementara padding dan hierarki teks menjaga keterbacaan.")
    add_heading(doc, "4.4 Hubungan dengan Siklus Hidup dan Instance State", 2)
    add_body(doc, "Pilihan dikirim saat SecondActivity dibuat dan dibaca pada onCreate(). Ketika activity dibuat ulang, Intent tetap tersedia sehingga pilihan dapat dibaca kembali. Posisi gulir view ber-ID dapat dipulihkan sistem; state lain dapat disimpan lewat onSaveInstanceState().")
    add_heading(doc, "4.5 Navigasi dan Keamanan Komponen", 2)
    add_body(doc, "parentActivityName mengaktifkan navigasi Up. SecondActivity memakai exported=false karena bersifat internal, sedangkan MainActivity exported=true karena memiliki intent-filter launcher.")
    add_heading(doc, "4.6 Batasan Pengujian", 2)
    add_body(doc, "Laporan mendokumentasikan sumber, resource, dan rancangan tampilan. Sesuai permintaan pengguna, APK tidak dijalankan; pengujian runtime, rotasi, Back/Up, dan posisi scroll dilakukan praktikan di Android Studio sebelum pengumpulan.")

    doc.add_page_break()
    add_heading(doc, "5. Kesimpulan", 1)
    add_body(doc, "Praktikum Labsheet 9-10 menghasilkan aplikasi Android dengan dua activity. MainActivity menyediakan tiga tombol Text One, Text Two, dan Text Three. Seluruh tombol membuka SecondActivity melalui intent eksplisit, sedangkan nilai intent extra menentukan satu dari tiga bagian teks yang ditampilkan.")
    add_body(doc, "Penggunaan ScrollView membuat bacaan panjang tetap dapat diakses pada layar kecil. Konfigurasi parent activity mendukung navigasi Up, sementara exported=false pada SecondActivity membatasi akses komponen secara tepat. Implementasi ini menunjukkan bahwa intent extra dapat memindahkan konteks sederhana antarlayar tanpa membuat activity tujuan yang berulang.")
    add_body(doc, "Pemahaman siklus hidup activity dan instance state membantu menjaga kontinuitas antarmuka saat activity dibuat ulang. Callback lifecycle digunakan untuk menempatkan logika sesuai keadaan layar, sedangkan Bundle dan mekanisme penyimpanan lain dipilih berdasarkan lama data harus bertahan.")

    add_heading(doc, "6. Daftar Pustaka", 1)
    refs = [
        "Modul Praktikum Pemrograman Perangkat Seluler Pertemuan 9-10. (2026). STMIK Widya Cipta Dharma Samarinda.",
        "Android Developers. (2026). The activity lifecycle. https://developer.android.com/guide/components/activities/activity-lifecycle",
        "Android Developers. (2026). Save UI states. https://developer.android.com/topic/libraries/architecture/saving-states",
        "Android Developers. (2026). Intents and intent filters. https://developer.android.com/guide/components/intents-filters",
    ]
    for ref in refs:
        p = doc.add_paragraph(style="Body Text")
        p.paragraph_format.left_indent = Cm(1.25)
        p.paragraph_format.first_line_indent = Cm(-1.25)
        p.add_run(ref)

    settings = doc.settings.element
    update = OxmlElement("w:updateFields")
    update.set(qn("w:val"), "true")
    settings.append(update)

    doc.save(OUTPUT)
    print(OUTPUT)


if __name__ == "__main__":
    main()
