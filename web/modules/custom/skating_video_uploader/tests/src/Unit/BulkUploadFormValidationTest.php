<?php

declare(strict_types=1);

namespace Drupal\Tests\skating_video_uploader\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Tests URL validation logic for the bulk upload form.
 *
 * @group skating_video_uploader
 * @group upload_form
 * @coversDefaultClass \Drupal\skating_video_uploader\Form\BulkUploadForm
 */
class BulkUploadFormValidationTest extends UnitTestCase {

  /**
   * Test YouTube URL validation pattern.
   *
   * @dataProvider youtubeUrlProvider
   */
  public function testYouTubeUrlValidation(string $url, bool $expected): void {
    $pattern = '/^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
    $result = (bool) preg_match($pattern, $url);
    $this->assertEquals($expected, $result, "URL validation failed for: $url");
  }

  /**
   * Data provider for YouTube URL validation tests.
   *
   * @return array
   *   Array of test cases with [url, expected_result].
   */
  public function youtubeUrlProvider(): array {
    return [
      // Valid URLs.
      ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', TRUE],
      ['https://youtube.com/watch?v=dQw4w9WgXcQ', TRUE],
      ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', TRUE],
      ['https://youtu.be/dQw4w9WgXcQ', TRUE],
      ['http://www.youtube.com/watch?v=dQw4w9WgXcQ', TRUE],
      ['http://youtu.be/dQw4w9WgXcQ', TRUE],
      ['www.youtube.com/watch?v=dQw4w9WgXcQ', TRUE],
      ['youtube.com/watch?v=dQw4w9WgXcQ', TRUE],
      ['https://www.youtube.com/embed/dQw4w9WgXcQ', TRUE],
      ['https://www.youtube.com/v/dQw4w9WgXcQ', TRUE],

      // Invalid URLs.
      ['https://vimeo.com/123456789', FALSE],
      ['https://www.dailymotion.com/video/x123456', FALSE],
      ['https://invalid-url.com', FALSE],
      ['not a url', FALSE],
      ['https://www.youtube.com/shorts', FALSE],
      ['https://www.youtube.com/watch?v=short', FALSE],
      ['https://www.youtube.com/watch?v=', FALSE],
      ['https://youtu.be/', FALSE],
    ];
  }

  /**
   * Test file extension validation.
   *
   * @dataProvider fileExtensionProvider
   */
  public function testFileExtensionValidation(string $filename, bool $expected): void {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $result = in_array($ext, $allowedExtensions);
    $this->assertEquals($expected, $result, "Extension validation failed for: $filename");
  }

  /**
   * Data provider for file extension validation tests.
   *
   * @return array
   *   Array of test cases with [filename, expected_result].
   */
  public function fileExtensionProvider(): array {
    return [
      // Valid extensions.
      ['photo.jpg', TRUE],
      ['photo.JPG', TRUE],
      ['photo.jpeg', TRUE],
      ['photo.png', TRUE],
      ['photo.gif', TRUE],
      ['video.mp4', TRUE],
      ['video.mov', TRUE],
      ['video.avi', TRUE],
      ['my-file.with.dots.jpg', TRUE],

      // Invalid extensions.
      ['document.pdf', FALSE],
      ['archive.zip', FALSE],
      ['executable.exe', FALSE],
      ['script.php', FALSE],
      ['no-extension', FALSE],
    ];
  }

}
