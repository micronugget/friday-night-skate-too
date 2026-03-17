<?php

namespace Drupal\videojs_media;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a VideoJS Media entity.
 */
interface VideoJsMediaInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the VideoJS media name.
   *
   * @return string
   *   The name of the VideoJS media item.
   */
  public function getName();

  /**
   * Sets the VideoJS media name.
   *
   * @param string $name
   *   The VideoJS media name.
   *
   * @return \Drupal\videojs_media\VideoJsMediaInterface
   *   The called VideoJS media entity.
   */
  public function setName($name);

  /**
   * Gets the VideoJS media creation timestamp.
   *
   * @return int
   *   Creation timestamp of the VideoJS media item.
   */
  public function getCreatedTime();

  /**
   * Sets the VideoJS media creation timestamp.
   *
   * @param int $timestamp
   *   The VideoJS media creation timestamp.
   *
   * @return \Drupal\videojs_media\VideoJsMediaInterface
   *   The called VideoJS media entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the VideoJS media published status indicator.
   *
   * @return bool
   *   TRUE if the VideoJS media item is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a VideoJS media item.
   *
   * @param bool $published
   *   TRUE to set this entity to published, FALSE to unpublished.
   *
   * @return \Drupal\videojs_media\VideoJsMediaInterface
   *   The called VideoJS media entity.
   */
  public function setPublished($published = NULL);

}
