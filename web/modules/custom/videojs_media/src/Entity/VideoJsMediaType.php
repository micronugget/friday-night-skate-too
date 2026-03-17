<?php

namespace Drupal\videojs_media\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the VideoJS Media type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "videojs_media_type",
 *   label = @Translation("VideoJS Media type"),
 *   label_collection = @Translation("VideoJS Media types"),
 *   label_singular = @Translation("VideoJS Media type"),
 *   label_plural = @Translation("VideoJS Media types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count VideoJS Media type",
 *     plural = "@count VideoJS Media types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\videojs_media\Form\VideoJsMediaTypeForm",
 *       "edit" = "Drupal\videojs_media\Form\VideoJsMediaTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\videojs_media\ListBuilder\VideoJsMediaTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer videojs media types",
 *   bundle_of = "videojs_media",
 *   config_prefix = "type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/videojs-media/types/add",
 *     "edit-form" = "/admin/structure/videojs-media/types/{videojs_media_type}",
 *     "delete-form" = "/admin/structure/videojs-media/types/{videojs_media_type}/delete",
 *     "collection" = "/admin/structure/videojs-media/types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *   }
 * )
 */
class VideoJsMediaType extends ConfigEntityBundleBase {

  /**
   * The machine name of this VideoJS Media type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the VideoJS Media type.
   *
   * @var string
   */
  protected $label;

  /**
   * A brief description of this VideoJS Media type.
   *
   * @var string
   */
  protected $description;

  /**
   * Gets the description.
   *
   * @return string
   *   The description of this VideoJS Media type.
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Sets the description.
   *
   * @param string $description
   *   The description of this VideoJS Media type.
   *
   * @return $this
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

}
