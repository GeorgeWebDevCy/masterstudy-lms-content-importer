# MasterStudy LMS (Free & Pro) Reference

> Last reviewed against `masterstudy-lms-learning-management-system` v3.6.26 and `masterstudy-lms-learning-management-system-pro` v4.7.20 on the local filesystem (see paths below).

## Paths

- Free plugin root: `/home/george/Downloads/masterstudy-lms-learning-management-system`
- Pro plugin root: `/home/george/Downloads/masterstudy-lms-learning-management-system-pro`

---

## Free Plugin (`masterstudy-lms-learning-management-system`)

### Bootstrapping & Execution Flow

- `masterstudy-lms-learning-management-system.php` defines core constants (`MS_LMS_VERSION`, `MS_LMS_PATH`, `MS_LMS_URL`) and loads Composer autoloaders plus the two main entry points:
  - `includes/init.php` (new namespaced architecture).
  - `_core/init.php` (legacy core stack).
- `includes/init.php` instantiates `MasterStudy\Lms\Plugin` with a REST router, loads route definitions from `includes/routes.php`, and includes common hook files (`actions.php`, `filters.php`, `enqueue.php`, `Hooks/export_import.php`).
- `_core/init.php` declares additional constants (`STM_LMS_*`), loads the legacy classes, helper functions, Elementor integration, WPBakery shortcodes, settings screens, and admin utilities. This layer still powers most of the front-end output and global helper functions.

### Directory Overview (high level)

| Directory | Purpose |
| --- | --- |
| `includes/` | Modern namespaced code: routing, REST controllers, repositories, models, validation, and shared utilities. |
| `_core/` | Legacy LMS engine with procedural helpers, template loader, Elementor/WPB widgets, classic REST/DB helpers, and admin UI. |
| `_core/lms/classes/` | Primary service classes (courses, lessons, quizzes, cart, users, etc.) that expose global `STM_LMS_*` APIs. |
| `_core/stm-lms-templates/` | Blade-like PHP templates rendered via `STM_LMS_Templates::show_lms_template()`. |
| `assets/` | Gutenberg blocks, JS, CSS, images. |
| `vendor/` | Composer dependencies (autoload, support libraries). |

### Data Model Summary

#### Custom Post Types (`_core/includes/post_type/posts.php`, `includes/Plugin/PostType.php`)

| Slug | Description | Menu / Access Notes |
| --- | --- | --- |
| `stm-courses` | Course container post type. Supports title, editor, excerpt, thumbnail, revisions, author. | Menu under `admin.php?page=stm-lms-settings`. |
| `stm-lessons` | Individual lesson units. | Visibility locked unless instructor/owner/admin (`STM_Lms_Post_Type`). |
| `stm-quizzes` | Quiz definitions (meta-driven). | Hidden from front-end search. |
| `stm-questions` | Question bank entries. | Used by quizzes; supports taxonomies. |
| `stm-reviews` | Course reviews (internal). | Hidden from menus except LMS settings. |
| `stm-orders` | Order records (internal). | UI suppressed. |
| Pro-only additions | `stm-assignments`, `stm-course-bundles`, `stm-ent-groups`, etc. via pro add-ons. | Enabled when pro add-ons are activated. |

#### Taxonomies (`_core/includes/post_type/taxonomies.php`, `includes/Plugin/Taxonomy.php`)

| Taxonomy | Applies To | Notes |
| --- | --- | --- |
| `stm_lms_course_taxonomy` | Courses | Hierarchical, slug configurable via LMS options, extended with term meta fields (`settings/course_taxonomy.php`). |
| `stm_lms_question_taxonomy` | Questions | Hierarchical categories for question bank / quiz bank. |

#### Custom Database Tables (`includes/Database/*`)

| Table | Model | Purpose |
| --- | --- | --- |
| `wp_stm_lms_curriculum_sections` | `CurriculumSection` | Stores course curriculum sections (title, `course_id`, display order). |
| `wp_stm_lms_curriculum_materials` | `CurriculumMaterial` | Stores ordered lesson/quiz associations per section (`post_id`, `post_type`, `section_id`). |

Additional tables (user course progress, quiz attempts, payouts, etc.) live in `_core/libraries/db` but are beyond the curriculum scope.

#### Key Meta Fields for Importers

| Entity | Key / Property | Meta key / Storage | Reference |
| --- | --- | --- | --- |
| Course | Certificate | `course_certificate` | `includes/Repositories/CourseRepository.php` |
| Course | Duration info text | `duration_info` | same as above |
| Course | Requirements | `requirements` | "" |
| Course | Intended audience | `intended_audience` | "" |
| Course | Access duration (numeric) | `access_duration` | "" |
| Course | Devices info | `access_devices` | "" |
| Course | Status (`published`, `draft`, `coming_soon`) | `status` | "" |
| Course | Coming soon start/end | `status_dates_start`, `status_dates_end` | "" |
| Course | Expiration flag | `expiration_course` (`on`/empty) | "" |
| Course | Expiration days | `end_time` (days) | "" |
| Course | Feature flag | `featured` (`on`/empty) | "" |
| Course | Lock lessons | `lock_lesson` (`on`/empty) | "" |
| Course | Level | `level` | "" |
| Course | Pricing | meta keys handled by `PricingRepository`: `price`, `sale_price`, `sale_price_dates_start`, `sale_price_dates_end`, `single_sale`, `not_membership`, `points_price`, `enterprise_price`, `affiliate_course`, etc. | `includes/Repositories/PricingRepository.php` |
| Lesson | Type (`text`, `video`, `zoom`, etc.) | `type` | `includes/Repositories/LessonRepository.php` |
| Lesson | Duration string | `duration` | "" |
| Lesson | Preview flag | `preview` (bool stored as `on`) | "" |
| Lesson | Lesson excerpt | `lesson_excerpt` | "" |
| Lesson | Video/audio meta (provider, IDs, captions, required progress) | `video_type`, `video`, `video_required_progress`, `audio_type`, `audio_required_progress`, `video_captions_ids`, `pdf_file_ids`, `pdf_read_all` | `LessonRepository` + `VideoTrait` |
| Quiz | Attempts allowed | `quiz_attempts` / `attempts` | `includes/Repositories/QuizRepository.php` |
| Quiz | Passing grade | `passing_grade` | "" |
| Quiz | Duration & measure | `duration`, `duration_measure` | "" |
| Quiz | Randomization flags | `random_questions`, `random_answers` | "" |
| Quiz | Required question IDs | `required_answers_ids` | "" |
| Quiz | Questions list | Stored as comma-separated IDs in `questions` meta | "" |
| Question | Question type | `type` | `includes/Repositories/QuestionRepository.php` |
| Question | Answers payload | Serialized JSON string in `answers` | "" |
| Question | Hint / explanation | `question_hint`, `question_explanation` | "" |
| Question | Media | `image`, `question_video`, `video_type`, `question_vimeo_url`, `question_youtube_url`, etc. | "" |
| Question | Taxonomies | WordPress term assignments under `stm_lms_question_taxonomy` | "" |

**Tip:** Repositories cast boolean values to `'on'`/empty strings when persisting, so mirror that behaviour if you bypass the repositories.

### Namespaced Service Layer (`includes/`)

| Component | Key files | Responsibilities |
| --- | --- | --- |
| `MasterStudy\Lms\Plugin` | `includes/Plugin.php` | Registers taxonomies, attaches REST routes, handles addon registration (`masterstudy_lms_plugin_addons` filter), exposes router instance. |
| Routing | `includes/Routing/*` | Thin wrapper over WP REST API. `Router` loads routes from PHP files. Middleware classes (Authentication, Instructor, PostGuard) enforce login, instructor capabilities, and per-post access. |
| Models | `includes/Models/*.php` | DTOs describing Course, Curriculum, Lesson, etc., used by serializers/controllers. |
| Database layer | `includes/Database/*` | Query builder around custom tables, providing fluent interface (`AbstractQuery`, `Query`). |
| Repositories | `includes/Repositories/*.php` | CRUD logic for courses, curriculum sections/materials, lessons, quizzes, questions, pricing, FAQs, certificates, etc. These orchestrate post insertion, meta updates, file attachments, and dispatch key actions. |
| REST Controllers | `includes/Http/Controllers/*` | REST endpoints for course builder, curriculum management, students, lessons/quizzes/questions, media uploads, templates, orders, comments, etc. Controllers mainly proxy to repositories and format responses. |
| Serializers | `includes/Http/Serializers/*` | Convert repository objects to API arrays (e.g., curriculum sections/materials). |
| Validation | `includes/Validation/*` | Simple rule-based validation for request payloads. |
| Utility | `includes/Utility/*` | Helpers (sanitizer, file handling, traits for video fields). |

Notable repository hooks (for extending importer logic):

- `masterstudy_lms_course_saved` (`includes/Repositories/CourseRepository.php`).
- `masterstudy_lms_course_update_access` (`CourseRepository::updateAccess`).
- `masterstudy_lms_course_price_updated` (`PricingRepository::save`).
- `masterstudy_lms_curriculum_material_created`, `masterstudy_lms_curriculum_material_updated`, `masterstudy_lms_curriculum_material_before_delete` (`CurriculumMaterialRepository`).
- `masterstudy_lms_save_lesson` (`LessonRepository`).
- `masterstudy_lms_save_quiz` (`QuizRepository`).
- `masterstudy_lms_custom_fields_updated` (`CustomFieldsRepository`).
- `masterstudy_lms_course_video_saved` (`includes/Http/Controllers/Course/UpdateSettingsController.php` after saving course media).

### Legacy Core (`_core/`)

- `lms/main.php` pulls together helpers and class singletons such as `STM_LMS_Course`, `STM_LMS_Lesson`, `STM_LMS_Quiz`, `STM_LMS_User`, `STM_LMS_Templates`, etc.
- `lms/helpers.php` provides procedural utilities (option getters, addon checks `is_ms_lms_addon_enabled()`, curriculum formatting, WPML helpers, etc.).
- `lms/classes/course.php` orchestrates user enrolment, price rendering, WPML duplication, WP query helpers, and REST search. Hooks into various actions (`stm_lms_archive_card_price`, `stm_lms_course_tabs`, `masterstudy_course_page_header`, etc.).
- `lms/classes/lesson.php`, `quiz.php`, `cart.php`, `order.php` manage runtime logic for lessons/quizzes/orders, including user progress tracking and event dispatch (e.g., `masterstudy_plugin_student_course_completion`).
- `includes/post_type/posts.php` and `taxonomies.php` register CPTs/taxonomies at init, injecting instructor-based menu visibility.
- `stm-lms-templates/` contains the PHP views rendered on the front end (course pages, dashboards, modals, analytics, etc.). Override using filters (`stm_lms_template_file`) or by copying templates into your theme.
- `settings/` defines the option panels (WPCFTO), metaboxes, payment settings, demo importers, wizard flows.

### Hooks & Filters (selected)

| Name | Type | Location | Purpose |
| --- | --- | --- | --- |
| `masterstudy_lms_plugin_addons` | filter | `includes/actions.php` & Pro | Allows registering addon objects. Pro/Plus append additional addons through this filter. |
| `masterstudy_lms_plugin_loaded` | action | `includes/actions.php` | Fires after the plugin bootstraps (`$plugin` instance passed). Pro routes/templates hook here. |
| `masterstudy_lms_map_api_data` | filter | `includes/filters.php` | Sanitizes API payload values; used when mapping post content and answers. |
| `stm_lms_post_types_array` | filter | `_core/includes/post_type/posts.php` | Modify CPT registration array (add custom CPT arguments). |
| `stm_lms_taxonomies` | filter | `_core/includes/post_type/taxonomies.php` & `includes/Plugin/Taxonomy.php` | Modify taxonomy registration config. |
| `masterstudy_lms_course_curriculum` | filter | `includes/Repositories/CurriculumRepository.php` | Adjust serialized curriculum sections/materials before returning. |
| `masterstudy_lms_course_price_updated` | action | `includes/Repositories/PricingRepository.php` | Triggered after pricing meta is saved; useful for cache invalidation. |
| `masterstudy_lms_save_lesson` | action | `includes/Repositories/LessonRepository.php` | Fires after lessons are created/updated (legacy compatibility). |
| `masterstudy_lms_save_quiz` | action | `includes/Repositories/QuizRepository.php` | Fires after quiz creation/update. |
| `add_user_course` | action | `_core/lms/classes/course.php` | Dispatched when a user is enrolled in a course. |
| `masterstudy_lms_course_player_register_assets` | action | `_core/stm-lms-templates/course-player.php` etc. | Register custom assets before rendering the course player. |
| `masterstudy_lms_curriculum_material_created/updated/before_delete` | actions | `includes/Repositories/CurriculumMaterialRepository.php` | Hook into curriculum changes (section assignments). |

Numerous additional hooks exist in the legacy helpers and templates; use `rg "do_action"` and `rg "apply_filters"` for deeper exploration.

### REST API (`masterstudy-lms/v2`)

- Base namespace: `/wp-json/masterstudy-lms/v2`.
- Global middleware: `Authentication` (requires logged-in users), `Instructor` (ensures instructor role), `PostGuard` (guards ownership or co-instructor access).
- Responses are handled through `MasterStudy\Lms\Routing\Router` and `WpResponseFactory`.

#### Course Builder & Settings

| Method | Route | Controller |
| --- | --- | --- |
| GET | `/healthcheck` | `MasterStudy\Lms\Http\Controllers\HealthCheckController` |
| GET | `/course-builder/settings` | `CourseBuilder\GetSettingsController` |
| PUT | `/course-builder/custom-fields/{post_id}` | `CourseBuilder\UpdateCustomFieldsController` |
| GET | `/courses/new` | `Course\AddNewController` |
| GET | `/instructor-courses` | `Course\GetInstructorCoursesController` |
| POST | `/courses/create` | `Course\CreateController` |
| POST | `/courses/category` | `Course\CreateCategoryController` |
| GET | `/courses/{course_id}/edit` | `Course\EditController` |
| GET | `/courses/{course_id}/settings` | `Course\GetSettingsController` |
| PUT | `/courses/{course_id}/settings` | `Course\UpdateSettingsController` |
| GET | `/courses/{course_id}/settings/faq` | `Course\GetFaqSettingsController` |
| PUT | `/courses/{course_id}/settings/faq` | `Course\UpdateFaqSettingsController` |
| PUT | `/courses/{course_id}/settings/certificate` | `Course\UpdateCertificateSettingsController` |
| PUT | `/courses/{course_id}/settings/course-page-style` | `Course\UpdatePageStyleSettingsController` |
| GET | `/courses/{course_id}/settings/pricing` | `Course\GetPricingSettingsController` |
| PUT | `/courses/{course_id}/settings/pricing` | `Course\UpdatePricingSettingsController` |
| PUT | `/courses/{course_id}/settings/files` | `Course\UpdateFilesSettingsController` |
| PUT | `/courses/{course_id}/settings/access` | `Course\UpdateAccessSettingsController` |
| PUT | `/courses/{course_id}/status` | `Course\UpdateStatusController` |

#### Curriculum Management

| Method | Route | Controller |
| --- | --- | --- |
| GET | `/courses/{course_id}/curriculum` | `Course\Curriculum\GetCurriculumController` |
| POST | `/courses/{course_id}/curriculum/section` | `Course\Curriculum\CreateSectionController` |
| PUT | `/courses/{course_id}/curriculum/section` | `Course\Curriculum\UpdateSectionController` |
| DELETE | `/courses/{course_id}/curriculum/section/{section_id}` | `Course\Curriculum\DeleteSectionController` |
| POST | `/courses/{course_id}/curriculum/material` | `Course\Curriculum\CreateMaterialController` |
| PUT | `/courses/{course_id}/curriculum/material` | `Course\Curriculum\UpdateMaterialController` |
| DELETE | `/courses/{course_id}/curriculum/material/{material_id}` | `Course\Curriculum\DeleteMaterialController` |
| GET | `/courses/{course_id}/curriculum/import` | `Course\Curriculum\ImportSearchController` |
| POST | `/courses/{course_id}/curriculum/import` | `Course\Curriculum\ImportMaterialsController` |
| GET | `/courses/{course_id}/announcement` | `Course\GetAnnouncementController` |
| PUT | `/courses/{course_id}/announcement` | `Course\UpdateAnnouncementController` |

#### Students & Enrolments

| Method | Route | Controller |
| --- | --- | --- |
| GET | `/students/` | `Student\GetStudentsController` |
| GET | `/students/{course_id}` | `Student\GetStudentsController` |
| GET | `/export/students/` | `Student\ExportStudentsController` |
| GET | `/students/export/{course_id}` | `Student\ExportStudentsController` |
| POST | `/student/{course_id}` | `Student\AddStudentController` |
| PUT | `/student/progress/{course_id}/{student_id}` | `Student\SetStudentProgressController` |
| DELETE | `/student/progress/{course_id}/{student_id}` | `Student\ResetStudentProgressController` |
| DELETE | `/student/{course_id}/{student_id}` | `Student\DeleteStudentController` |
| DELETE | `/students/delete/` | `Student\DeleteStudentsController` (bulk) |
| GET | `/student/stats/{student_id}` | `Student\GetStudentStatsController` |

#### Lessons, Quizzes, Questions

| Method | Route | Controller |
| --- | --- | --- |
| POST | `/lessons` | `Lesson\CreateController` |
| PUT | `/lessons/{lesson_id}` | `Lesson\UpdateController` |
| GET | `/lessons/{lesson_id}` | `Lesson\GetController` |
| POST | `/quizzes` | `Quiz\CreateController` |
| GET | `/quizzes/{quiz_id}` | `Quiz\GetController` |
| PUT | `/quizzes/{quiz_id}` | `Quiz\UpdateController` |
| DELETE | `/quizzes/{quiz_id}` | `Quiz\DeleteController` |
| PUT | `/quizzes/{quiz_id}/questions` | `Quiz\UpdateQuestionsController` |
| GET | `/enrolled-quizzes` | `Quiz\GetEnrolledQuizzesController` |
| GET | `/quiz/attempts` | `Quiz\GetQuizAttemptsController` |
| GET | `/quiz/attempt` | `Quiz\GetQuizAttemptController` |
| GET | `/questions/categories` | `Question\GetCategoriesController` |
| POST | `/questions/category` | `Question\CreateCategoryController` |
| POST | `/questions` | `Question\CreateController` |
| POST | `/questions/bulk` | `Question\BulkCreateController` |
| GET | `/questions/{question_id}` | `Question\GetController` |
| PUT | `/questions/{question_id}` | `Question\UpdateController` |
| DELETE | `/questions/{question_id}` | `Question\DeleteController` |

#### Media, Templates, Comments, Misc.

| Method | Route | Controller |
| --- | --- | --- |
| GET | `/certificates` | `Certificates\GetCertificatesController` |
| DELETE | `/certificates/{certificate_id}` | `Certificates\DeleteCertificateController` |
| POST | `/media` | `Media\UploadController` |
| POST | `/media/from-url` | `Media\UploadFromUrlController` |
| DELETE | `/media/{media_id}` | `Media\DeleteController` |
| POST | `/create-template` | `Course\CourseTemplate\CreateCourseTemplateController` |
| POST | `/modify-template` | `Course\CourseTemplate\ModifyCourseTemplateController` |
| PUT | `/update-template` | `Course\CourseTemplate\UpdateCourseTemplateController` |
| POST | `/duplicate-template` | `Course\CourseTemplate\DuplicateCourseTemplateController` |
| POST | `/page-to-course-template` | `Course\CourseTemplate\SavePageToCourseTemplateController` |
| POST | `/assign-category-template` | `Course\CourseTemplate\AssignCategoryToTemplateController` |
| DELETE | `/delete-template/{template_id}` | `Course\CourseTemplate\DeleteCourseTemplateController` |
| GET | `/{post_id}` | `Comment\GetController` |
| POST | `/{post_id}` | `Comment\CreateController` |
| POST | `/{comment_id}/reply` | `Comment\ReplyController` |
| POST | `/{comment_id}/approve` | `Comment\ApproveController` |
| POST | `/{comment_id}/unapprove` | `Comment\UnapproveController` |
| POST | `/{comment_id}/spam` | `Comment\SpamController` |
| POST | `/{comment_id}/unspam` | `Comment\UnspamController` |
| POST | `/{comment_id}/trash` | `Comment\TrashController` |
| POST | `/{comment_id}/untrash` | `Comment\UntrashController` |
| POST | `/{comment_id}/update` | `Comment\UpdateController` |
| GET | `/course-levels` | `Blocks\Course\GetLevelsController` |
| GET | `/course-categories` | `Blocks\Course\GetCategoriesController` |
| GET | `/settings` | `Blocks\GetSettingsController` |

#### Orders & Reporting

| Method | Route | Controller |
| --- | --- | --- |
| GET | `/all-orders` | `Order\GetOrdersController` |
| GET | `/orders/{order_id}` | `Order\GetOrderController` |
| PUT | `/orders/{order_id}` | `Order\UpdateOrderController` |
| POST | `/orders-bulk-update` | `Order\BulkUpdateOrdersController` |
| GET | `/orders` | `Order\GetUserOrdersController` (instructor scope) |
| GET | `/courses` | `Course\GetCoursesController` |
| GET | `/users` | `Order\GetUserOrdersController` |
| GET | `/instructor-public-courses` | `Course\GetInstructorPublicCoursesController` |
| GET | `/instructor-reviews` | `Review\GetInstructorReviewsController` |
| GET | `/student-courses` | `Course\GetStudentCoursesController` |

### Template System

- **Renderer:** `STM_LMS_Templates::show_lms_template( $slug, $args )` (core) resolves templates under `_core/stm-lms-templates/`.
- **Override filter:** `add_filter( 'stm_lms_template_file', 'callback', 10, 2 )` to point to a different base path (Pro uses this to inject its own templates).
- **Key template groups** (subdirectories under `_core/stm-lms-templates/`): `account/`, `course-player/`, `courses/`, `components/`, `analytics/`, `modals/`, `questions/`, `quiz/`, `checkout/`, `pmpro/`, `elementor-widgets/`, `wizard/`.
- **Email templates**: `_core/stm-lms-templates/emails/`.
- **Analytics & dashboards** rely on Vue/JS assets registered in `_core/lms/enqueue.php` and data endpoints described above.

---

## Pro Plugin (`masterstudy-lms-learning-management-system-pro`)

### Initialization & Licensing

- `masterstudy-lms-learning-management-system-pro.php` defines constants (`STM_LMS_PRO_*`), loads Composer autoloader, and includes `includes/init.php`.
- `includes/init.php` handles Freemius/AppSumo license checks (`mslms_verify`), loads compatibility shims, hooks, enqueue helpers, and conditionally loads `pro.php` (core Pro features) and `plus.php` (Pro Plus addons) when LMS free core is active.
- Activation hook `set_stm_admin_notification_ms_lms()` sets up transient-based admin notices.

### Structure Highlights

| Directory | Purpose |
| --- | --- |
| `addons/` | Individual Pro addon packages (Assignments, Drip Content, CourseBundle, Enterprise Courses, Zoom, etc.) each with their own `main.php`, routes, actions, filters. |
| `addons-plus/` | Pro Plus addons (AI Lab, AudioLesson, ComingSoon+, Email Branding, Google Meet, Grades, Question Media, Social Login). |
| `includes/` | Core Pro logic: hooks, WooCommerce integrations, announcements, certificates, helper functions, nonce utilities, Pro routes. |
| `rest-api/` | Additional REST routes for analytics, orders, lesson markers. |
| `stm-lms-templates/` | Template overrides/extenders for analytics, gradebook, bundles, course player enhancements, etc. |

### Addon Registration

- `includes/pro.php` merges the following addon classes into the `masterstudy_lms_plugin_addons` filter: Assignments, Certificate Builder, Sequential Drip, Email Manager, Gradebook, Live Streams, Media Library, Multi Instructors, Prerequisite, SCORM, Shareware, Zoom Conference, Course Bundle.
- `includes/plus/filters.php` appends Pro Plus addon classes (Google Meet, Coming Soon, Question Media, Social Login, Audio Lesson, Grades, AI Lab).
- Enabled addons are tracked in the `stm_lms_addons` option (same as free plugin); use `is_ms_lms_addon_enabled( $slug )` to check status.

### Hooks & Filters of Interest

| Name | Type | Location | Purpose |
| --- | --- | --- | --- |
| `stm_lms_template_file` | filter | `includes/hooks/templates.php` | Override template lookup to favor Pro template directory. |
| `masterstudy_lms_plugin_loaded` | action | `includes/pro.php`, `includes/plus/actions.php` | Loads Pro/Plus REST routes (`rest-api/Routes/Orders.php`, `Analytics.php`, `LessonMarkers.php`). |
| `masterstudy_lms_course_video_saved` | action | `includes/plus/actions.php` | Saves Pro-specific course preview meta (video poster/source). |
| `stm_lms_menu_items` | filter | `includes/plus/filters.php` | Adds Analytics/My Sales menu entries in user account navigation. |
| `masterstudy_show_analytics_templates` | action | `includes/plus/actions.php` & template hooks | Injects analytics template fragments into instructor dashboards. |
| `stm_lms_get_sale_price` / `stm_lms_sale_price_meta` | filters | `includes/hooks/sale-price.php` | Enforce scheduled sale price windows (start/end timestamps). |

### Additional REST Endpoints (loaded onto the same `/wp-json/masterstudy-lms/v2` namespace)

#### Analytics & Reporting (`rest-api/Routes/Analytics.php`)

| Method | Route | Controller |
| --- | --- | --- |
| GET | `/analytics/users` | `Pro\RestApi\Http\Controllers\Analytics\GetUsersController` |
| POST | `/analytics/instructors` | `Analytics\Instructor\GetInstructorsController` |
| GET | `/analytics/revenue` | `Analytics\Revenue\GetRevenueController` |
| GET | `/analytics/revenue/payouts` | `Analytics\Revenue\GetPayoutsController` |
| POST | `/analytics/revenue/courses` | `Analytics\Revenue\GetCoursesController` |
| POST | `/analytics/revenue/groups` | `Analytics\Revenue\GetGroupsController` |
| POST | `/analytics/revenue/bundles` | `Analytics\Revenue\GetBundlesController` |
| POST | `/analytics/revenue/students` | `Analytics\Revenue\GetStudentsController` |
| GET | `/analytics/engagement` | `Analytics\Engagement\GetEngagementController` |
| POST | `/analytics/engagement/courses` | `Analytics\Engagement\GetCoursesController` |
| POST | `/analytics/engagement/students` | `Analytics\Engagement\GetStudentsController` |
| GET | `/analytics/instructor/short-report` | `Analytics\Instructor\GetInstructorReportController` |
| GET | `/analytics/instructor/{instructor_id}/data` | `Analytics\Instructor\GetInstructorDataController` |
| POST | `/analytics/instructor/{instructor_id}/courses` | `Analytics\Revenue\GetCoursesController` |
| POST | `/analytics/students` | `Analytics\Student\GetStudentsController` |
| GET | `/analytics/course/{course_id}/data` | `Analytics\Course\GetCourseDataController` |
| POST | `/analytics/course/{course_id}/lessons` | `Analytics\Course\GetCourseLessonsController` |
| POST | `/analytics/course/{course_id}/lessons-by-users` | `Analytics\Course\GetLessonsByUsersController` |
| GET | `/analytics/reviews-charts` | `Analytics\Review\GetReviewChartsController` |
| POST | `/analytics/reviews-courses` | `Analytics\Review\GetCoursesController` |
| POST | `/analytics/reviews-users` | `Analytics\Review\GetUsersController` |
| POST | `/analytics/reviews-{status}` | `Analytics\Review\GetReviewsController` |
| POST | `/analytics/instructor-students` | `Analytics\Student\GetInstructorStudentsController` |
| GET | `/analytics/student/{user_id}/data` | `Analytics\Student\GetStudentDataController` |
| POST | `/analytics/student/{user_id}/courses` | `Analytics\Student\GetStudentCoursesController` |
| POST | `/analytics/student/{user_id}/membership` | `Analytics\Student\GetStudentMembershipController` |
| POST | `/analytics/instructor-orders` | `Analytics\InstructorOrders\GetInstructorOrdersController` |
| GET | `/analytics/bundle/{bundle_id}/data` | `Analytics\Bundle\GetBundleDataController` |
| POST | `/analytics/bundle/{bundle_id}/courses` | `Analytics\Bundle\GetBundleCoursesController` |
| POST | `/analytics/course/{bundle_id}/bundles` | `Analytics\Bundle\GetCourseBundlesController` |

#### Orders, Lesson Markers (`rest-api/Routes/Orders.php`, `LessonMarkers.php`)

| Method | Route | Controller |
| --- | --- | --- |
| GET | `/orders/woocommerce-orders` | `Pro\RestApi\Http\Controllers\Orders\GetOrdersController` |
| GET | `/lesson/markers/get/{lesson_id}` | `Pro\RestApi\Http\Controllers\Lessons\Markers\GetController` |
| POST | `/lesson/markers/create/{lesson_id}` | `Lessons\VideoQuestions\CreateController` |
| PUT | `/lesson/markers/update/{marker_id}` | `Lessons\Markers\UpdateController` |
| DELETE | `/lesson/markers/delete/{lesson_id}/{marker_id}` | `Lessons\Markers\DeleteController` |
| PUT | `/lesson/markers/lock/{lesson_id}` | `Lessons\VideoQuestions\UpdateQuestionsLockController` |

### Template Overrides

Pro template root `stm-lms-templates/` includes overrides and additions for:

- `analytics/` (revenue, engagement, reviews, course/bundle/student dashboards).
- `gradebook/`, `grades/` (if Grades addon active).
- `lesson/` (extra layouts), `course-player/` (assignments, live streams, Google Meet).
- `bundles/`, `course/`, `account/`, `points/`, `google-meet/`, `multi_instructor/`.

When Pro is active, `includes/hooks/templates.php` ensures `STM_LMS_Templates` resolves these files first.

### WooCommerce & Commerce Integrations

- `includes/hooks/woocommerce.php` wires LMS enrolment with WooCommerce orders, leveraging helper classes in `includes/classes/class-woocommerce*.php`.
- Instructor-focused order detail endpoints (`instructor-order-details`, `instructor-sales-details`) are registered via rewrite endpoints and handled with LMS templates.

---

## Building an Automated Importer (Practical Notes)

1. **Leverage Repositories Where Possible**
   - Instantiate repository classes from the namespaced layer to avoid manual meta handling:
     ```php
     $courseRepo = new \MasterStudy\Lms\Repositories\CourseRepository();
     $lessonRepo = new \MasterStudy\Lms\Repositories\LessonRepository();
     $quizRepo   = new \MasterStudy\Lms\Repositories\QuizRepository();
     $curriculumSections = new \MasterStudy\Lms\Repositories\CurriculumSectionRepository();
     $curriculumMaterials = new \MasterStudy\Lms\Repositories\CurriculumMaterialRepository();
     ```
   - These repositories automatically sanitize data, persist meta, and fire legacy actions relied on by addons.

2. **Seats in Custom Tables**
   - After creating a course (`stm-courses`), insert curriculum sections/materials using the repositories so `wp_stm_lms_curriculum_sections` and `wp_stm_lms_curriculum_materials` remain in sync.
   - When deleting/reordering items, call repository methods to trigger `reorder()` logic and maintain sequential ordering.

3. **Meta Keys & Casting**
   - Follow the repository casting rules: booleans stored as `'on'`, numeric strings stored as strings, arrays serialized where expected (`video_captions_ids`, `pdf_file_ids`).
   - For lessons/quizzes/questions, rely on repository `create()`/`save()` so `masterstudy_lms_map_api_data` filter is applied (escapes backslashes, etc.).

4. **Hook into Lifecycle Actions**
   - Listen to `masterstudy_lms_course_saved`, `masterstudy_lms_save_lesson`, `masterstudy_lms_save_quiz`, `masterstudy_lms_curriculum_material_created` to perform post-import adjustments (e.g., syncing custom metadata, logging).
   - If importing WPML translations, use `CurriculumRepository::duplicate_curriculum()` or mimic the logic in `includes/Repositories/CurriculumRepository.php`.

5. **REST vs Direct DB**
   - Optionally call REST endpoints (requires instructor session + nonce) for course builder flows; refer to tables above for available routes.
   - When running from WP CLI or backend plugin code, direct repository usage is typically simpler and bypasses HTTP middleware.

6. **Addons Awareness**
   - Check add-on availability via `is_ms_lms_addon_enabled( 'slug' )`. Some importer fields (e.g., prerequisites, course bundles, assignments) only make sense when corresponding add-ons are active.
   - Pro Plus features register additional REST routes and templates—account for them if your importer populates analytics-related data (e.g., lesson markers, Google Meet sessions).

7. **Templates & Presentation**
   - Course front-end uses templates under `_core/stm-lms-templates/courses` and pro overrides. If you inject custom content, either:
     - Create appropriate meta consumed by existing templates (e.g., `announcement`, `course_page_style`), or
     - Override templates via theme or the `stm_lms_template_file` filter.

8. **WP Cron / Email Hooks**
   - Course completion or enrolment triggers emails via `_core/lms/classes/mails.php`. If the importer bulk-enrols students, consider deferring or disabling emails with addon settings or temporarily unhooking actions.

9. **Performance Considerations**
   - Large Word document imports may involve numerous media uploads. Use the REST media endpoints or WordPress media APIs to attach files, then reference attachment IDs in lesson meta arrays (`lesson_files`, `pdf_file_ids`, `video_captions_ids`).
   - Batch operations on curriculum tables should honor ordering; prefer repository `import()` helper for materials when inserting multiple lessons sequentially.

10. **Testing & Debugging**
    - Use `STM_LMS_Helpers::set_log()` (from legacy helpers) or `error_log` to trace importer steps.
    - AJAX/REST failures return `WP_REST_Response` objects with `error_code` fields (`unauthorized_access`, `forbidden_access` etc.) from middleware—use these to troubleshoot permission issues.

---

## Quick File Reference

- Free plugin bootstrap: `masterstudy-lms-learning-management-system.php`
- Namespaced plugin entry: `includes/Plugin.php`
- Route definitions: `includes/routes.php`
- Course repositories: `includes/Repositories/CourseRepository.php`, `CurriculumSectionRepository.php`, `CurriculumMaterialRepository.php`, `LessonRepository.php`, `QuizRepository.php`, `QuestionRepository.php`
- Legacy helpers: `_core/lms/helpers.php`
- Template loader: `_core/lms/classes/templates.php`
- Pro bootstrap: `masterstudy-lms-learning-management-system-pro.php`, `includes/pro.php`, `includes/plus.php`
- Pro REST routes: `rest-api/Routes/*.php`
- Pro template overrides: `stm-lms-templates/`

Use this document as a launchpad—the codebase is extensive, so rely on search tools (`rg`, IDE navigation) to dive deeper into the specific area you are integrating with.

