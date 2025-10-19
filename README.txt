=== MasterStudy LMS Content Importer ===
Contributors: orionaselite
Donate link: https://www.georgenicolaou.me/
Tags: lms, masterstudy, course import, docx, education
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.11.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Import structured DOCX course outlines into MasterStudy LMS with sections, lessons, and quizzes in one go.

== Description ==

MasterStudy LMS Content Importer lets you turn a Word document curriculum into a fully populated MasterStudy course in minutes. Upload any `.docx` outline (for example, the sample file bundled in `sample-data/Stress Resilience Curriculum_ALL MODULES.docx`), preview the detected structure, then confirm to create numbered modules/sections, lessons, and quizzes automatically.

**Highlights**

* Structured preview of modules, lessons, and quiz counts before anything is created.
* Uses the document table of contents when available, with manual fallback patterns for headings.
* Automatically numbers modules (`1.`, `2.` …) and lesson titles (`1.1`, `1.2` …) while retaining their original headings.
* Converts "Test" sections into MasterStudy quizzes, preserving the parsed questions.
* Lesson title templating with placeholders such as `%module_index%`, `%lesson_index%`, `%module_title%`, or `%lesson_source_title%`.
* Option to skip front matter pages so parsing begins exactly where your content starts.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it via the WordPress admin.
2. Activate **MasterStudy LMS Content Importer**.
3. Ensure the MasterStudy LMS plugin (free or pro) is active.
4. Go to **Course Importer** in the WordPress dashboard to upload your `.docx` outline.

== Frequently Asked Questions ==

= Do I need a specific document format? =

Any `.docx` file exported from Microsoft Word or compatible editors works. Using consistent heading styles and a table of contents improves detection, but you can also provide custom module/lesson identifiers in the importer settings.

= Can I adjust lesson names? =

Yes. Set the lesson title template before import and the plugin will apply it while numbering lessons automatically.

= What happens to "Test" sections? =

The importer renames them to quizzes, migrates detected questions, and attaches the quiz at the end of the corresponding section.

== Screenshots ==

1. Admin importer form with configuration options.
2. Course structure preview before confirmation.

== Changelog ==

= 1.11.0 =
* Added page skipping support and improved quiz title conversion.

== Upgrade Notice ==

= 1.11.0 =
Parsing now respects the "Skip pages before" option and converts tests to quizzes—update to keep imports accurate.

