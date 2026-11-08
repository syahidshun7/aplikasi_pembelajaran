from pathlib import Path
from zipfile import ZipFile

from PIL import Image, ImageDraw, ImageFont
from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Inches, Pt, RGBColor


ROOT = Path(__file__).resolve().parent
REFERENCE = Path(r"D:\STIMIK_WICIDA\Praktikum PPS\2443908_Syahid Hussein_Labsheet 9-10 PPS.docx")
OUTPUT = ROOT / "2443908_Syahid Hussein_Labsheet 7-8 PPS.docx"
ASSETS = ROOT / "assets"
ASSETS.mkdir(exist_ok=True)


def font(size, bold=False):
    path = Path(r"C:\Windows\Fonts\timesbd.ttf" if bold else r"C:\Windows\Fonts\times.ttf")
    if not path.exists():
        path = Path(r"C:\Windows\Fonts\arial.ttf")
    return ImageFont.truetype(str(path), size)


def extract_logo():
    out = ASSETS / "logo.png"
    with ZipFile(REFERENCE) as zf:
        out.write_bytes(zf.read("word/media/image1.png"))
    return out


def rounded(draw, box, radius, fill, outline=None, width=1):
    draw.rounded_rectangle(box, radius=radius, fill=fill, outline=outline, width=width)


def make_resource_map():
    out = ASSETS / "resource-map.png"
    img = Image.new("RGB", (1500, 680), "white")
    d = ImageDraw.Draw(img)
    title = font(40, True)
    label = font(25, True)
    small = font(20)
    d.text((65, 35), "Peta sumber daya pengembangan Android", fill="#172554", font=title)
    center = (565, 235, 935, 435)
    rounded(d, center, 25, "#173B69")
    d.text((650, 285), "ANDROID", fill="white", font=font(34, True))
    d.text((632, 340), "DEVELOPER", fill="#F5C05A", font=font(28, True))
    boxes = [
        (60, 170, 390, 360, "Design", "Material Design\nwarna, ikon, tipografi"),
        (1110, 170, 1440, 360, "Develop", "API, panduan, referensi\ndan contoh kode"),
        (585, 485, 915, 650, "Distribute", "publikasi, Play Store\ndan pertumbuhan pengguna"),
    ]
    for x1, y1, x2, y2, head, body in boxes:
        rounded(d, (x1, y1, x2, y2), 20, "#F8FAFC", "#173B69", 4)
        w = d.textbbox((0, 0), head, font=label)[2]
        d.text(((x1+x2-w)/2, y1+25), head, fill="#173B69", font=label)
        y = y1 + 85
        for line in body.split("\n"):
            w = d.textbbox((0, 0), line, font=small)[2]
            d.text(((x1+x2-w)/2, y), line, fill="#475569", font=small)
            y += 34
    for a, b in [((390, 265), (555, 315)), ((1110, 265), (945, 315)), ((750, 435), (750, 475))]:
        d.line((*a, *b), fill="#C98A23", width=7)
    img.save(out)
    return out


def make_activity_flow():
    out = ASSETS / "activity-flow.png"
    img = Image.new("RGB", (1500, 500), "white")
    d = ImageDraw.Draw(img)
    d.text((65, 30), "Alur membuat dan memulai Activity", fill="#172554", font=font(40, True))
    labels = [
        ("Deklarasi", "Activity dicatat\npada manifest"),
        ("Intent", "Objek permintaan\nmenuju kelas tujuan"),
        ("startActivity", "Sistem membuat atau\nmenampilkan Activity"),
        ("onCreate", "Layout dipasang dan\nstate diinisialisasi"),
    ]
    x_positions = [55, 420, 785, 1150]
    for x, (head, body) in zip(x_positions, labels):
        rounded(d, (x, 155, x+295, 390), 20, "#F8FAFC", "#173B69", 4)
        w = d.textbbox((0, 0), head, font=font(24, True))[2]
        d.text((x+(295-w)/2, 190), head, fill="#173B69", font=font(24, True))
        y = 260
        for line in body.split("\n"):
            w = d.textbbox((0, 0), line, font=font(19))[2]
            d.text((x+(295-w)/2, y), line, fill="#475569", font=font(19))
            y += 34
    for x in [350, 715, 1080]:
        d.line((x+8, 272, x+58, 272), fill="#C98A23", width=7)
        d.polygon([(x+58, 272), (x+38, 258), (x+38, 286)], fill="#C98A23")
    img.save(out)
    return out


def shade(cell, fill):
    pr = cell._tc.get_or_add_tcPr()
    el = OxmlElement("w:shd")
    el.set(qn("w:fill"), fill)
    pr.append(el)


def add_page_number(section):
    p = section.footer.paragraphs[0]
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run()
    begin = OxmlElement("w:fldChar")
    begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = " PAGE "
    end = OxmlElement("w:fldChar")
    end.set(qn("w:fldCharType"), "end")
    run._r.extend([begin, instr, end])


def add_toc(doc):
    p = doc.add_paragraph()
    r = p.add_run()
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
    r._r.extend([begin, instr, separate, end])


def setup_styles(doc):
    for name in ["Normal", "Body Text", "List Bullet"]:
        style = doc.styles[name]
        style.font.name = "Times New Roman"
        style._element.rPr.rFonts.set(qn("w:eastAsia"), "Times New Roman")
        style.font.size = Pt(12)
    doc.styles["Body Text"].paragraph_format.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
    doc.styles["List Bullet"].paragraph_format.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
    for level, size in [(1, 16), (2, 12), (3, 12)]:
        s = doc.styles[f"Heading {level}"]
        s.font.name = "Times New Roman"
        s._element.rPr.rFonts.set(qn("w:eastAsia"), "Times New Roman")
        s.font.size = Pt(size)
        s.font.bold = True
        s.font.color.rgb = RGBColor(0, 0, 0)
        s.paragraph_format.space_before = Pt(9)
        s.paragraph_format.space_after = Pt(5)
        s.paragraph_format.keep_with_next = True
    cap = doc.styles["Caption"]
    cap.font.name = "Times New Roman"
    cap._element.rPr.rFonts.set(qn("w:eastAsia"), "Times New Roman")
    cap.font.size = Pt(10)
    cap.font.italic = True
    cap.font.color.rgb = RGBColor(0, 0, 0)


def heading(doc, text, level=1):
    return doc.add_heading(text, level)


def body(doc, text, indent=True):
    p = doc.add_paragraph(style="Body Text")
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.space_after = Pt(4)
    if indent:
        p.paragraph_format.first_line_indent = Cm(1.25)
    p.add_run(text)
    return p


def bullet(doc, text):
    p = doc.add_paragraph(style="List Bullet")
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.left_indent = Cm(1)
    p.paragraph_format.first_line_indent = Cm(-0.45)
    p.paragraph_format.space_after = Pt(3)
    p.add_run(text)
    return p


def code(doc, text, caption):
    table = doc.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = table.cell(0, 0)
    shade(cell, "F4F6FA")
    p = cell.paragraphs[0]
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after = Pt(4)
    p.paragraph_format.line_spacing = 1.0
    r = p.add_run(text)
    r.font.name = "Consolas"
    r._element.rPr.rFonts.set(qn("w:eastAsia"), "Consolas")
    r.font.size = Pt(8.5)
    cp = doc.add_paragraph(caption, style="Caption")
    cp.alignment = WD_ALIGN_PARAGRAPH.CENTER


def figure(doc, path, caption, width=6.25):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run().add_picture(str(path), width=Inches(width))
    cp = doc.add_paragraph(caption, style="Caption")
    cp.alignment = WD_ALIGN_PARAGRAPH.CENTER


def main():
    logo = extract_logo()
    resource_map = make_resource_map()
    activity_flow = make_activity_flow()
    doc = Document()
    setup_styles(doc)

    cover = doc.sections[0]
    cover.page_width = Inches(8.5)
    cover.page_height = Inches(11)
    cover.left_margin = Inches(0.97)
    cover.right_margin = Inches(1.18)
    cover.top_margin = Inches(1.22)
    cover.bottom_margin = Inches(0.9)
    cover.different_first_page_header_footer = True

    for text, size, gap in [
        ("LAPORAN AKHIR PRAKTIKUM", 15, 10),
        ("PEMROGRAMAN PERANGKAT SELULER\nPERTEMUAN KE- LABSHEET 7-8 (PPS)", 15, 14),
    ]:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_after = Pt(gap)
        r = p.add_run(text)
        r.bold = True
        r.font.name = "Times New Roman"
        r.font.size = Pt(size)
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run().add_picture(str(logo), width=Inches(2.55))

    table = doc.add_table(rows=3, cols=3)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    values = [("Nim", ":", "2443908"), ("Nama Mahasiswa", ":", "Syahid Hussein"), ("Kelas/ Kelompok", ":", "TI/MA/1")]
    for row, vals in zip(table.rows, values):
        for cell, value in zip(row.cells, vals):
            cell.text = value
            for p2 in cell.paragraphs:
                p2.paragraph_format.space_after = Pt(0)
                for r in p2.runs:
                    r.font.name = "Times New Roman"
                    r.font.size = Pt(12)
            pr = cell._tc.get_or_add_tcPr()
            borders = OxmlElement("w:tcBorders")
            for edge in ["top", "left", "bottom", "right"]:
                el = OxmlElement("w:" + edge)
                el.set(qn("w:val"), "nil")
                borders.append(el)
            pr.append(borders)
    doc.add_paragraph()
    doc.add_paragraph()
    for text in ["PROGRAM STUDI TEKNIK INFORMATIKA", "STMIK WIDYA CIPTA DHARMA SAMARINDA", "2026"]:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_after = Pt(8)
        r = p.add_run(text)
        r.font.name = "Times New Roman"
        r.font.size = Pt(13)

    sec = doc.add_section(WD_SECTION.NEW_PAGE)
    sec.page_width = Inches(8.5)
    sec.page_height = Inches(11)
    sec.left_margin = Inches(1)
    sec.right_margin = Inches(1)
    sec.top_margin = Inches(0.85)
    sec.bottom_margin = Inches(0.8)
    add_page_number(sec)
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("DAFTAR ISI")
    r.bold = True
    r.font.size = Pt(16)
    add_toc(doc)

    doc.add_page_break()
    heading(doc, "1. Dasar Teori", 1)
    body(doc, "Android menyediakan sumber daya pengembangan yang membantu pengembang merancang antarmuka, memahami API, memakai contoh kode, mengelola SDK, dan mempersiapkan distribusi aplikasi. Sumber daya ini perlu dipahami berdasarkan tujuan penggunaannya agar keputusan teknis dan desain tidak hanya mengikuti contoh, tetapi sesuai kebutuhan aplikasi.")
    heading(doc, "1.1 Sumber Daya Android", 2)
    body(doc, "Dokumentasi Android mengelompokkan materi pengembangan ke dalam area desain, pengembangan, dan distribusi. Area desain membahas pola visual, warna, ikon, tipografi, serta pedoman Material Design. Area pengembangan memuat dokumentasi API, tutorial, referensi alat, panduan arsitektur, dan contoh kode. Area distribusi membahas publikasi, kualitas rilis, Play Store, dan pengembangan basis pengguna.")
    figure(doc, resource_map, "Gambar 1.1 Peta fungsi sumber daya pengembangan Android.")
    heading(doc, "1.2 Android Template", 2)
    body(doc, "Template Android Studio menyediakan struktur awal activity, layout, resource, dan konfigurasi yang mengikuti pola umum. Empty Activity cocok untuk proyek yang membutuhkan awal minimal, sedangkan Basic Activity biasanya menyediakan app bar, menu, content layout, dan komponen tambahan. Template menghemat waktu, tetapi pengembang tetap perlu memahami berkas yang dibuat agar dapat menyesuaikannya dengan aman.")
    heading(doc, "1.3 SDK Manager", 2)
    body(doc, "SDK Manager dipakai untuk memilih platform Android, build tools, emulator, sumber dokumentasi, dan update site. Setiap proyek memiliki compile SDK dan target SDK tertentu, sehingga paket yang diperlukan harus tersedia. Dokumentasi offline membantu ketika koneksi internet terbatas, tetapi versinya perlu diperbarui agar sesuai dengan API terbaru yang digunakan.")

    heading(doc, "1.4 Membuat dan Memulai Activity", 2)
    body(doc, "Activity adalah komponen Android yang menyajikan satu layar interaksi. Activity harus dideklarasikan pada AndroidManifest.xml, memiliki kelas Java atau Kotlin, dan biasanya memasang layout pada onCreate(). Activity dapat dimulai dengan intent eksplisit ketika kelas tujuan diketahui oleh aplikasi.")
    figure(doc, activity_flow, "Gambar 1.2 Alur deklarasi, intent, dan pemanggilan callback Activity.")
    code(doc, "Intent intent = new Intent(MainActivity.this, DetailActivity.class);\nstartActivity(intent);", "Gambar 1.3 Contoh intent eksplisit untuk memulai Activity.")
    body(doc, "Ketika startActivity() dipanggil, sistem menyelesaikan intent, menyiapkan activity tujuan, lalu memanggil callback siklus hidupnya. onCreate() digunakan untuk memasang layout dan inisialisasi awal. Navigasi kembali dikelola melalui back stack, sehingga tombol Back biasanya menutup activity teratas dan menampilkan activity sebelumnya.")
    heading(doc, "1.5 Resource Aplikasi", 2)
    body(doc, "Resource aplikasi dipisahkan dari kode program agar mudah dipelihara dan dilokalkan. String ditempatkan pada res/values/strings.xml, warna pada colors.xml, tema pada themes.xml, gambar pada drawable, ikon launcher pada mipmap, menu pada res/menu, dan layout pada res/layout. Kode mengakses resource melalui kelas R yang dihasilkan saat proses build.")

    heading(doc, "2. Prosedur Eksplorasi", 1)
    steps = [
        "Mempelajari fungsi area Design, Develop, dan Distribute pada sumber daya Android.",
        "Membandingkan tujuan template Empty Activity dan Basic Activity.",
        "Mengidentifikasi hubungan antara tampilan template dan berkas activity_main.xml, content_main.xml, menu_main.xml, strings.xml, AndroidManifest.xml, serta MainActivity.",
        "Menelaah cara mengubah nama aplikasi dan warna toolbar melalui resource, bukan menuliskan nilai berulang di kode.",
        "Mempelajari penambahan activity melalui New, Activity, kemudian Gallery di Android Studio.",
        "Menelaah intent eksplisit yang digunakan untuk membuka activity baru.",
        "Mempelajari fasilitas Import Sample untuk melihat contoh aplikasi dan struktur proyek.",
        "Menelaah SDK Platforms, SDK Tools, dan SDK Update Sites pada SDK Manager.",
        "Menyusun hasil kajian tanpa membuat atau menjalankan proyek, sesuai ruang lingkup laporan yang diminta.",
    ]
    for i, text in enumerate(steps, 1):
        p = doc.add_paragraph(style="Body Text")
        p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        p.paragraph_format.left_indent = Cm(0.8)
        p.paragraph_format.first_line_indent = Cm(-0.8)
        p.add_run(f"{i}. ").bold = True
        p.add_run(text)

    heading(doc, "2.1 Berkas yang Dikaji", 2)
    for path in ["AndroidManifest.xml", "MainActivity.java atau MainActivity.kt", "res/layout/activity_main.xml", "res/layout/content_main.xml", "res/menu/menu_main.xml", "res/values/strings.xml", "res/values/colors.xml", "Gradle build files"]:
        bullet(doc, path)

    doc.add_page_break()
    heading(doc, "3. Data / Hasil Kajian", 1)
    heading(doc, "3.1 Pemetaan Bagian Template", 2)
    table = doc.add_table(rows=1, cols=3)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.style = "Table Grid"
    headers = ["Bagian", "Fungsi", "Lokasi Utama"]
    for i, h in enumerate(headers):
        table.rows[0].cells[i].text = h
        shade(table.rows[0].cells[i], "DCE6F1")
        for r in table.rows[0].cells[i].paragraphs[0].runs:
            r.bold = True
    rows = [
        ("Status bar", "Informasi sistem", "Dikelola sistem Android"),
        ("Toolbar", "Identitas dan navigasi layar", "activity_main.xml / theme"),
        ("Nama aplikasi", "Label aplikasi", "strings.xml dan manifest"),
        ("Menu opsi", "Perintah tambahan", "res/menu dan callback activity"),
        ("CoordinatorLayout", "Koordinasi perilaku child view", "activity_main.xml"),
        ("Content view", "Isi utama aplikasi", "content_main.xml"),
        ("FAB", "Aksi utama kontekstual", "layout dan onClick listener"),
    ]
    for vals in rows:
        cells = table.add_row().cells
        for i, v in enumerate(vals):
            cells[i].text = v
    for row in table.rows:
        for cell in row.cells:
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
            for p2 in cell.paragraphs:
                for r in p2.runs:
                    r.font.name = "Times New Roman"
                    r.font.size = Pt(10.5)
    cp = doc.add_paragraph("Tabel 3.1 Pemetaan komponen template dan sumber kodenya.", style="Caption")
    cp.alignment = WD_ALIGN_PARAGRAPH.CENTER

    heading(doc, "3.2 Kategori Resource", 2)
    table2 = doc.add_table(rows=1, cols=3)
    table2.style = "Table Grid"
    table2.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, h in enumerate(["Direktori", "Isi", "Manfaat"]):
        table2.rows[0].cells[i].text = h
        shade(table2.rows[0].cells[i], "DCE6F1")
        table2.rows[0].cells[i].paragraphs[0].runs[0].bold = True
    resources = [
        ("res/layout", "Struktur UI XML", "Memisahkan tampilan dari logika"),
        ("res/drawable", "Gambar dan shape", "Mendukung aset adaptif"),
        ("res/mipmap", "Ikon launcher", "Memilih densitas ikon yang tepat"),
        ("res/values", "String, warna, dimensi, tema", "Konsistensi dan lokalisasi"),
        ("res/menu", "Definisi item menu", "Navigasi dan aksi terstruktur"),
    ]
    for vals in resources:
        cells = table2.add_row().cells
        for i, v in enumerate(vals):
            cells[i].text = v
    for row in table2.rows:
        for cell in row.cells:
            for p2 in cell.paragraphs:
                for r in p2.runs:
                    r.font.name = "Times New Roman"
                    r.font.size = Pt(10.5)
    cp = doc.add_paragraph("Tabel 3.2 Kategori resource utama dalam proyek Android.", style="Caption")
    cp.alignment = WD_ALIGN_PARAGRAPH.CENTER

    heading(doc, "3.3 Menambahkan Activity dari Template", 2)
    body(doc, "Activity tambahan dapat dibuat dari galeri template. Android Studio menghasilkan kelas activity, layout, dan deklarasi manifest yang relevan. Setelah dibuat, pengembang perlu memeriksa nama package, exported, parent activity, tema, resource string, serta kode navigasi agar hasil template sesuai kebutuhan aplikasi.")
    code(doc, "<activity\n    android:name=\".DetailActivity\"\n    android:exported=\"false\"\n    android:parentActivityName=\".MainActivity\" />", "Gambar 3.1 Contoh deklarasi Activity internal pada manifest.")

    doc.add_page_break()
    heading(doc, "3.4 Mengubah Identitas dan Warna Aplikasi", 2)
    body(doc, "Nama aplikasi sebaiknya didefinisikan pada strings.xml dan direferensikan oleh atribut android:label. Warna toolbar dan komponen lain sebaiknya berasal dari color resource atau theme attribute. Cara ini menghindari hard-coded value dan memudahkan perubahan tema maupun mode gelap.")
    code(doc, '<string name="app_name">Eksplorasi Android</string>\n<color name="primary">#173B69</color>', "Gambar 3.2 Contoh resource nama aplikasi dan warna utama.")
    heading(doc, "3.5 Eksplorasi SDK Manager", 2)
    for text in [
        "SDK Platforms menyediakan platform API Android dan system image yang diperlukan.",
        "SDK Tools menyediakan build tools, platform tools, emulator, command-line tools, dan dokumentasi tambahan.",
        "SDK Update Sites menentukan sumber pembaruan yang diperiksa Android Studio.",
        "Paket yang dipasang sebaiknya disesuaikan dengan compile SDK, target pengujian, dan kebutuhan proyek agar penyimpanan tidak terpakai berlebihan.",
    ]:
        bullet(doc, text)
    heading(doc, "3.6 Hasil Laporan Awal", 2)
    body(doc, "Membuat dan memulai Activity memerlukan empat unsur yang saling terkait: deklarasi komponen, kelas activity, layout atau composable, serta intent pemanggil. Template membantu menyiapkan unsur tersebut, tetapi manifest dan resource tetap harus ditinjau agar navigasi, keamanan komponen, dan identitas aplikasi benar.")

    doc.add_page_break()
    heading(doc, "4. Analisis dan Pembahasan", 1)
    heading(doc, "4.1 Manfaat Template", 2)
    body(doc, "Template mempercepat pembuatan struktur awal dan memperkenalkan pola UI yang umum. Namun, template bukan pengganti pemahaman arsitektur. Komponen yang tidak diperlukan dapat menambah kompleksitas, sehingga setiap activity, menu, dependency, dan resource hasil template perlu dievaluasi.")
    heading(doc, "4.2 Pemisahan Resource dan Kode", 2)
    body(doc, "Pemisahan resource dari kode mendukung lokalisasi, konsistensi tema, perubahan desain, dan variasi perangkat. String dan warna yang dipusatkan lebih mudah diaudit daripada nilai yang ditulis berulang. Sistem resource qualifier juga memungkinkan Android memilih aset sesuai bahasa, densitas, orientasi, dan ukuran layar.")
    heading(doc, "4.3 Activity dan Intent", 2)
    body(doc, "Intent eksplisit tepat untuk navigasi internal karena tujuan telah diketahui. Activity tujuan yang tidak perlu diakses aplikasi lain sebaiknya tidak diekspor. Parent activity membantu navigasi Up, sedangkan back stack mempertahankan urutan layar. Data sederhana dapat dikirim melalui intent extra, tetapi data besar sebaiknya tidak dipindahkan melalui Bundle.")
    heading(doc, "4.4 SDK dan Dokumentasi", 2)
    body(doc, "SDK Manager menghubungkan konfigurasi proyek dengan alat build dan platform Android. Ketidaksesuaian versi dapat menyebabkan proses sinkronisasi atau build gagal. Dokumentasi resmi harus menjadi acuan utama karena API, persyaratan target SDK, dan praktik distribusi berubah dari waktu ke waktu.")
    heading(doc, "4.5 Batasan", 2)
    body(doc, "Laporan ini berfokus pada kajian materi dan rancangan penerapan sumber daya Android. Tidak ada proyek atau pengujian runtime yang dibuat, sesuai permintaan pengguna. Oleh karena itu, hasil yang disajikan berupa pemetaan konsep, contoh konfigurasi, dan analisis prosedur.")

    doc.add_page_break()
    heading(doc, "5. Kesimpulan", 1)
    body(doc, "Eksplorasi sumber daya Android menunjukkan bahwa dokumentasi, template, resource proyek, contoh kode, dan SDK Manager saling melengkapi. Dokumentasi menjelaskan konsep dan API, template menyediakan struktur awal, resource memisahkan tampilan dari logika, sedangkan SDK Manager menyediakan platform dan alat yang diperlukan.")
    body(doc, "Activity dapat dibuat dari template atau secara manual, kemudian dimulai menggunakan intent. Manifest, kelas activity, layout, dan resource harus diperiksa sebagai satu kesatuan. Pemahaman hubungan ini membuat pengembang lebih mampu menyesuaikan template tanpa bergantung pada konfigurasi bawaan.")
    body(doc, "Penerapan resource yang terstruktur meningkatkan kemudahan pemeliharaan, konsistensi tampilan, dukungan lokalisasi, dan adaptasi perangkat. Sebelum digunakan pada proyek nyata, versi SDK dan dokumentasi perlu disesuaikan dengan kebutuhan serta kebijakan Android terbaru.")

    heading(doc, "6. Daftar Pustaka", 1)
    refs = [
        "Modul Praktikum Pemrograman Perangkat Seluler Pertemuan 7-8. (2026). STMIK Widya Cipta Dharma Samarinda.",
        "Android Developers. (2026). Develop for Android. https://developer.android.com/",
        "Android Developers. (2026). Introduction to activities. https://developer.android.com/guide/components/activities/intro-activities",
        "Android Developers. (2026). App resources overview. https://developer.android.com/guide/topics/resources/providing-resources",
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
