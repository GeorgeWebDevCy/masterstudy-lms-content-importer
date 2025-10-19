<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ );
}

if ( ! function_exists( 'wpautop' ) ) {
    function wpautop( string $text ): string {
        $text = trim( $text );

        if ( '' === $text ) {
            return '';
        }

        $paragraphs = preg_split( "/\n{2,}/", $text );

        if ( false === $paragraphs ) {
            $paragraphs = array( $text );
        }

        $paragraphs = array_map(
            static function ( string $paragraph ): string {
                $paragraph = str_replace( "\n", "<br />\n", $paragraph );

                return '<p>' . $paragraph . '</p>';
            },
            $paragraphs
        );

        return implode( "\n", $paragraphs );
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( string $text ): string {
        $text = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $text );

        if ( null === $text ) {
            $text = '';
        }

        return strip_tags(
            $text,
            '<p><br><a><img><strong><em><ul><ol><li><blockquote><code>'
        );
    }
}

require_once dirname( __DIR__ ) . '/includes/class-masterstudy-lms-content-importer-docx-parser.php';

$parser = new Masterstudy_Lms_Content_Importer_Docx_Parser();

$reflection = new ReflectionClass( $parser );
$method     = $reflection->getMethod( 'format_block' );
$method->setAccessible( true );

function assert_same( $expected, $actual, string $message ): void {
    if ( $expected !== $actual ) {
        fwrite( STDERR, "Assertion failed: {$message}\nExpected: {$expected}\nActual: {$actual}\n" );
        exit( 1 );
    }
}

function assert_contains( string $needle, string $haystack, string $message ): void {
    if ( false === strpos( $haystack, $needle ) ) {
        fwrite( STDERR, "Assertion failed: {$message}\nNeedle: {$needle}\nHaystack: {$haystack}\n" );
        exit( 1 );
    }
}

function assert_not_contains( string $needle, string $haystack, string $message ): void {
    if ( false !== strpos( $haystack, $needle ) ) {
        fwrite( STDERR, "Assertion failed: {$message}\nUnexpected needle: {$needle}\nHaystack: {$haystack}\n" );
        exit( 1 );
    }
}

$raw_url_result = $method->invoke( $parser, array( 'https://example.com/resource' ) );
assert_same( '<p>https://example.com/resource</p>', $raw_url_result, 'Standalone URLs should remain raw for auto-embed.' );

$youtube_result = $method->invoke( $parser, array( 'https://youtu.be/abc123' ) );
assert_contains( '[embed]https://youtu.be/abc123[/embed]', $youtube_result, 'YouTube URLs should be wrapped in embed shortcodes.' );

$anchor_line   = '<a href="https://example.com">Example</a>';
$anchor_result = $method->invoke( $parser, array( $anchor_line ) );
assert_contains( $anchor_line, $anchor_result, 'Anchor tags should remain untouched.' );

$youtube_anchor   = '<a href="https://www.youtube.com/watch?v=dBn0ETS6XDk" target="_blank" rel="noopener">https://www.youtube.com/watch?v=dBn0ETS6XDk</a>';
$youtube_anchor_result = $method->invoke( $parser, array( $youtube_anchor ) );
assert_contains( '[embed]https://www.youtube.com/watch?v=dBn0ETS6XDk[/embed]', $youtube_anchor_result, 'YouTube anchor tags should be converted into embed shortcodes.' );

$composite_result = $method->invoke( $parser, array( 'Intro text', 'https://vimeo.com/12345' ) );
assert_contains( '<p>Intro text</p>', $composite_result, 'Non-URL lines should remain as paragraphs.' );
assert_contains( '[embed]https://vimeo.com/12345[/embed]', $composite_result, 'Vimeo URLs should be wrapped in embed shortcodes.' );

$sanitized_result = $method->invoke(
    $parser,
    array(
        '<script>alert("x")</script>',
        '<img src="https://example.com/image.jpg" alt="Example" />',
        '<a href="https://example.com">Example Link</a>',
    )
);

assert_not_contains( '<script', $sanitized_result, 'Script tags should be stripped from the output.' );
assert_contains( '<img src="https://example.com/image.jpg" alt="Example" />', $sanitized_result, 'Images should remain in the sanitized output.' );
assert_contains( '<a href="https://example.com">Example Link</a>', $sanitized_result, 'Anchor tags should remain after sanitization.' );

fwrite( STDOUT, "All format_block tests passed.\n" );
