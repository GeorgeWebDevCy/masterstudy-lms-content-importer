# masterstudy-lms-content-importer

Accepts a Word Document and imports it as a MasterStudy course (sections, lessons, quizzes).

## Lesson templating and identifier patterns

The importer now respects the configuration provided from the admin screen:

- **Lesson title template** – controls how each lesson title is generated. Use placeholders such as `%module%`, `%lesson%`, or `%index%` to inject the module heading, detected lesson heading, or a 1-based lesson counter respectively. If the template resolves to an empty string, the importer falls back to the parsed lesson heading.
- **Identifier patterns** – provide one pattern per line to hint how module and lesson headings should be detected while parsing the DOCX document. Plain text entries perform a case-insensitive substring match; expressions wrapped in slashes are treated as regular expressions. Prefix entries with `module:` or `lesson:` to tie a hint to a specific type, otherwise the pattern is applied to both.

When a module contains multiple detected lessons, each lesson is inserted sequentially before the quiz so quizzes remain associated with the correct section order. The importer keeps the quiz material attached to the same section regardless of how many lessons were inserted ahead of it.

Both options are sanitised in the admin layer before being passed to the importer, so developers integrating programmatically should mimic that behaviour (trim strings and filter out empty pattern definitions) to ensure consistent results.
