=== MasterStudy LMS Content Importer ===
Contributors: orionaselite
Donate link: https://www.georgenicolaou.me/
Tags: lms, masterstudy, course import, docx, education
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.13.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Import structured DOCX course outlines into MasterStudy LMS with sections, lessons, and quizzes in one go.

== Description ==

MasterStudy LMS Content Importer lets you turn a Word document curriculum into a fully populated MasterStudy course in minutes. Upload any `.docx` outline (for example, the sample file bundled in `sample-data/Stress Resilience Curriculum_ALL MODULES.docx`), preview the detected structure, then confirm to create numbered modules/sections, lessons, and quizzes automatically.

**Highlights**

* Structured preview of modules, lessons, and quiz counts before anything is created.
* Uses the document heading hierarchy (H1 → modules, H2/H3 → lessons), with optional identifier patterns for unusual structures.
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

= 1.13.2 =
* Convert DOCX media reference lines (for example, “Video: "Title" (YouTube) : URL”) into embedded players and strip the helper text.

= 1.13.1 =
* Convert YouTube hyperlinks into embedded videos during DOCX imports.

= 1.13.0 =
* Preserve hyperlinks from DOCX lessons when creating MasterStudy posts.
* Import embedded media references (YouTube, Vimeo, and other supported providers) directly into lesson content.
* Attach lesson images discovered in DOCX content to their respective lessons.

= 1.12.4 =
* Bump plugin version.

= 1.12.3 =
* Default module titles now use the "MODULE X. Module Title" pattern throughout parsing and import previews.

= 1.12.2 =
* Prevent fatal errors when the first detected heading belongs to a module without an initialized lesson by skipping lesson finalization until both structures are available.

= 1.12.1 =
* Prevent fatal errors on servers missing the DOM PHP extension by showing a clear requirement message after file upload.

= 1.12.0 =
* Detect modules and lessons directly from heading levels without requiring a table of contents.
* Added page skip option and improved quiz title conversion.

== Upgrade Notice ==

= 1.13.2 =
Converts DOCX media reference lines into embedded players so lessons no longer show the raw helper text.

= 1.13.1 =
Ensures YouTube links embedded in DOCX lessons appear as playable videos after import.

= 1.13.0 =
Adds hyperlink, embedded media, and inline image support when importing DOCX lessons—update to keep multimedia content intact.

= 1.12.4 =
Version bump only.

= 1.12.3 =
Ensures all imported modules follow the "MODULE X. Module Title" naming pattern by default—update for consistent numbering.

= 1.12.2 =
Fixes a fatal error triggered during DOCX parsing when a module heading appears before any lessons—update to ensure imports complete successfully.

= 1.12.1 =
Shows a friendly message if the DOM PHP extension is missing instead of crashing after uploads—update to avoid fatal errors on restrictive hosts.

= 1.12.0 =
Parsing now derives modules/lessons from heading levels and respects the "Skip pages before" option—update for accurate imports.
