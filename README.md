# MasterStudy LMS Content Importer

Import DOCX course outlines straight into MasterStudy LMS. Upload a Word document, for example the "Stress Resilience Curriculum" file in `sample-data/`, review the detected structure, and confirm to create the full course (modules/sections, lessons, and quizzes) automatically.

## Key Features

- **Structured import preview** – Upload a `.docx` file and see exactly how the importer mapped modules, lessons, and quizzes before anything is created. Confirm or cancel the import with one click.
- **Module & lesson detection** – Uses the document table of contents when available. If headings are inconsistent, provide your own module/lesson identifiers (e.g. `Module` or `Lesson`) so the parser knows what to look for.
- **Numbered hierarchy** – Modules are numbered (`1.`, `2.` …) and each lesson is prefixed with its module/lesson index (`1.1`, `1.2` …) while preserving the original heading text.
- **Quiz conversion** – "Test" sections are automatically converted to MasterStudy quizzes, keeping all detected questions and attaching the quiz at the end of the corresponding section.
- **Lesson title templating** – Customize how lesson titles are generated with placeholders such as `%module_index%`, `%lesson_index%`, `%module_title%`, or `%lesson_source_title%`.
- **Page skipping** – Start parsing from a specific page so front matter, cover pages, or lengthy introductions are ignored.

## Usage

1. Install and activate **MasterStudy LMS Content Importer** alongside the free MasterStudy LMS plugin.
2. In the WordPress dashboard open **Course Importer** from the main menu.
3. Upload your `.docx` file.
4. (Optional) Adjust lesson title template, module/lesson identifiers, and the first page to parse.
5. Click **Import Course** to see the preview. Review the detected modules, lessons, and quiz counts.
6. Click **Confirm Import** to create the course.

The sample document located at `sample-data/Stress Resilience Curriculum_ALL MODULES.docx` is a good reference for structuring your own curriculum files.

## Customizing detection

- **Identifier patterns** – Give the importer hints about headings. Each line represents one pattern; prefix with `module:` or `lesson:` to scope the hint. Plain text performs a case-insensitive substring match, while patterns wrapped in `/.../` are treated as regular expressions.
- **Table of contents** – Leave "Use table of contents" enabled to rely on TOC entries first. Disable it when your document lacks a TOC or it does not match the desired structure.
- **Start page** – Set "Skip pages before" to the first page where real content begins; all earlier pages (covers, acknowledgements, etc.) are ignored.

## Requirements

- WordPress 5.8+
- MasterStudy LMS plugin (free or pro)

## Contributors

- [@orionaselite](https://profiles.wordpress.org/orionaselite)

## License

GPL-2.0-or-later

