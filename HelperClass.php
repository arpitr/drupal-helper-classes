<?php

namespace Drupal\helper_utility;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Link;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\ViewExecutableFactory;
use Drupal\views\Plugin\views\pager\Some;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\video_embed_field\Plugin\video_embed_field\Provider\Vimeo;
use Drupal\video_embed_field\Plugin\video_embed_field\Provider\YouTube;

/**
 * Class HelperUtility.
 *
 * @package Drupal\helper_utility
 */
class HelperUtility {

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The Executable view.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $viewExecutable;

  /**
   * The Current Route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The Translation Manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $translationManager;

  /**
   * GlobalCMSServices constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity Type manager.
   * @param \Drupal\language\ConfigurableLanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\views\ViewExecutableFactory $viewExecutable
   *   A view executable instance, from the loaded entity.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The route match service.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translationManager
   *   The translation manager.
   */
  public function __construct(EntityTypeManager $entityTypeManager, ConfigurableLanguageManagerInterface $language_manager, ViewExecutableFactory $viewExecutable, CurrentRouteMatch $currentRouteMatch, TranslationManager $translationManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $language_manager;
    $this->viewExecutable = $viewExecutable;
    $this->routeMatch = $currentRouteMatch;
    $this->translationManager = $translationManager;
  }

  /**
   * Custom function to check if given field exists in the given entity.
   *
   * @param object $entity
   *   Entity to check if field exists.
   * @param string $field_name
   *   Field name.
   *
   * @return bool
   *   Returns TRUE if field exists in entity, else FALSE.
   */
  public function checkFieldExists($entity, $field_name) {
    return $entity->hasField($field_name) ? TRUE : FALSE;
  }

  /**
   * Custom function to fetch image file url from the given media/image entity.
   *
   * @param object $entity
   *   Media/Image entity.
   *
   * @return string
   *   Returns image file uri.
   */
  public function fetchFileUri($entity) {
    if ($this->checkValidEntity($entity, 'media') && isset($entity->image) && $entity->image->entity) {
      $image_url = $entity->image->entity->getFileUri();
    }
    else {
      $image_url = $entity->getFileUri();
    }
    return $image_url;
  }

  /**
   * Custom function to fetch user-entered url from link field.
   *
   * Supports internal and external urls.
   *
   * @param object $entity
   *   Entity to fetch link.
   * @param string $field_name
   *   Link field.
   *
   * @return mixed
   *   Returns user-entered url.
   */
  public function fetchLinkUrl($entity, $field_name) {
    return $entity->$field_name->first()->getUrl()->toString();
  }

  /**
   * Custom function to get renderable responsive image.
   *
   * @param string $image_uri
   *   Image Uri.
   * @param string $image_style
   *   Responsive Image Style ID.
   * @param string $image_alt
   *   Image Alternative text.
   * @param string $image_title
   *   Image Title.
   *
   * @return array
   *   Returns renderable image.
   */
  public function getResponsiveImagePath($image_uri, $image_style, $image_alt, $image_title) {
    if ($image_uri) {
      // Rendering the responsive image style.
      return [
        '#theme' => 'responsive_image',
        '#attributes' => [
          'alt' => $image_alt,
          'title' => $image_title,
        ],
        '#responsive_image_style_id' => $image_style,
        '#uri' => $image_uri,
      ];
    }
  }

  /**
   * Custom function to get all referenced entities.
   *
   * @param object $entity
   *   Entity to check if field exists.
   * @param string $field_name
   *   Field name.
   * @param bool $shift
   *   Whether to send entity object or array of entity objects.
   *
   * @return mixed
   *   Returns array of entity objects/entity object.
   */
  public function getReferencedEntities($entity, $field_name, $shift = FALSE) {

    // Get the current language to pull translated data.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Get all referenced entities.
    $referenceEntities = $entity->$field_name->referencedEntities();
    $translatedReferenceEntities = [];
    foreach ($referenceEntities as $referenceEntity) {
      if ($referenceEntity->hasTranslation($langcode)) {
        $translatedReferenceEntities[] = $referenceEntity->getTranslation($langcode);
      }
      else {
        $translatedReferenceEntities[] = $referenceEntity;
      }
    }

    return $shift ? array_shift($translatedReferenceEntities) : $translatedReferenceEntities;
  }

  /**
   * Custom function to get the entity url.
   *
   * @param object $entity
   *   Entity to fetch link.
   *
   * @return mixed
   *   Returns system-generated url.
   */
  public function getEntityUrl($entity) {
    return $entity->toUrl()->toString();
  }

  /**
   * Custom function to get the entity url from Uri.
   *
   * @param string $uri
   *   Uri value.
   * @param string $title
   *   Title value.
   * @param bool $from_user
   *   Whether url needs to be generated from user input. Defaults to FALSE.
   *
   * @return mixed
   *   Returns system-generated url with options.
   */
  public function generateUrlFromUri($uri, $title, $from_user = FALSE) {
    if ($from_user) {
      $url = Url::fromUserInput($uri);
    }
    else {
      $url = Url::fromUri($uri);
    }
    if ($url) {
      if ($url->isExternal()) {
        $option = [
          '#attributes' => [
            'target' => '_blank',
            'rel' => 'nofollow',
            'title' => $title,
          ],
        ];
        $url = $url->mergeOptions($option);
        return $url;
      }
      else {
        $option = [
          '#attributes' => [
            'title' => $title,
          ],
        ];
        $url = $url->mergeOptions($option);
        return $url;
      }
    }
  }

  /**
   * Custom function to get the entity url from Route.
   *
   * @param object $entity_id
   *   Entity id to fetch link.
   * @param string $entity_type
   *   Entity Type.
   * @param string $title
   *   Title value.
   *
   * @return mixed
   *   Returns system-generated url.
   */
  public function generateUrlFromRoute($entity_id, $entity_type, $title) {
    $option = [
      '#attributes' => [
        'title' => $title,
      ],
    ];
    return Url::fromRoute('entity.' . $entity_type . '.canonical', [$entity_type => $entity_id])->mergeOptions($option);
  }

  /**
   * Custom function to get the entity link from Text and URL.
   *
   * @param string $text
   *   Text to create link name.
   * @param string $url
   *   Url to create link URL.
   *
   * @return mixed
   *   Returns system-generated link.
   */
  public function generateLinkFromTextAndUrl($text, $url) {
    $link = Link::fromTextAndUrl($text, $url);
    $link = $link->toRenderable();
    // If you need some attributes.
    $link['#attributes'] = ['title' => $text];
    return $link;
  }

  /**
   * Custom function to get the entity ID.
   *
   * @param object $entity
   *   Entity to get Id.
   *
   * @return mixed
   *   Returns system-generated ID of Entity.
   */
  public function getId($entity) {
    return $entity->id() ? $entity->id() : '';
  }

  /**
   * Custom function to get the entity Title.
   *
   * @param object $entity
   *   Entity to get title.
   *
   * @return mixed
   *   Returns title of Entity.
   */
  public function getTitle($entity) {
    return $entity->getName() ? $entity->getName() : '';
  }

  /**
   * Custom function to get the entity Type.
   *
   * @param object $entity
   *   Entity to get type.
   *
   * @return mixed
   *   Returns type of Entity.
   */
  public function getEntityType($entity) {
    return $entity->getType() ? $entity->getType() : '';
  }

  /**
   * Custom function to get the Alt and Title for media images.
   *
   * @param string $alt
   *   Alt value of image.
   * @param string $image_title
   *   Title value of image.
   * @param string $entity_title
   *   Title value of entity.
   *
   * @return mixed
   *   Returns Alt and Title for media image.
   */
  public function getAttributes($alt, $image_title, $entity_title) {
    $image = '';
    if ($alt) {
      if ($image_title) {
        // If alt and image title is found then return following.
        $image = [
          'alt' => $alt,
          'title' => $image_title,
        ];
        return $image;
      }
      // If alt is found but image title is not found then return following.
      $image = [
        'alt' => $alt,
        'title' => $alt,
      ];
      return $image;
    }
    elseif ($image_title) {
      // If image title is found but alt is not found then return following.
      $image = [
        'alt' => $image_title,
        'title' => $image_title,
      ];
      return $image;
    }
    else {
      // If alt and image title is not found then return following.
      $image = [
        'alt' => $entity_title,
        'title' => $entity_title,
      ];
      return $image;
    }
  }

  /**
   * Fetch Images along with its meta data.
   *
   * @param object $entity
   *   Media/Image entity.
   * @param string $image_field
   *   Machine name of image reference field.
   * @param string $responsive_image_style
   *   Responsive image style.
   * @param string $fallback_image_title
   *   Fallback text in case of image alt and title are not provided.
   *
   * @return array
   *   Returns images with its meta data.
   */
  public function buildImage($entity, $image_field, $responsive_image_style, $fallback_image_title) {
    $images = [];
    if ($this->checkFieldExists($entity, $image_field) && $entity->$image_field->entity) {
      $image_entities = $this->getReferencedEntities($entity, $image_field);
      foreach ($image_entities as $image_entity) {
        $image_attributes = [];
        $image_file_uri = '';
        if ($this->checkValidEntity($image_entity, 'media') && isset($image_entity->image)) {
          $image_attributes = $this->getAttributes($image_entity->image->alt, $image_entity->image->title, $fallback_image_title);
        }
        else {
          $image_attributes = $this->getAttributes($entity->$image_field->alt, $entity->$image_field->title, $fallback_image_title);
        }
        $image_alt = isset($image_attributes['alt']) ? $image_attributes['alt'] : '';
        $image_title = isset($image_attributes['title']) ? $image_attributes['title'] : '';
        $image_file_uri = $this->fetchFileUri($image_entity);
        if ($image_file_uri) {
          $images[] = $this->getResponsiveImagePath($image_file_uri, $responsive_image_style, $image_alt, $image_title);
        }
      }
      $images = (count($images) == 1) ? $images[0] : $images;
    }
    return $images;
  }

  /**
   * Fetch Images description.
   *
   * @param object $entity
   *   Media/Image entity.
   * @param string $image_field
   *   Machine name of image reference field.
   *
   * @return array
   *   Returns images description.
   */
  public function getImageDescription($entity, $image_field) {
    $image_description = [];
    if ($this->checkFieldExists($entity, $image_field) && $entity->$image_field->entity) {
      $image_entities = $this->getReferencedEntities($entity, $image_field);
      foreach ($image_entities as $image_entity) {
        if ($this->checkValidEntity($image_entity, 'media') && $this->checkFieldExists($image_entity, 'field_description') && $image_entity->field_description->value) {
          $image_description = $image_entity->field_description->processed;
        }
      }
      $image_description = (count($image_description) == 1) ? $image_description[0] : $image_description;
    }
    return $image_description;
  }

  /**
   * Fetch Resource details of given resource entity.
   *
   * @param object $resource
   *   Resource entity.
   * @param null|string $image_style
   *   Responsive image style.
   * @param bool $image
   *   Whether image is needed or not. Defaults to TRUE.
   * @param array $video_details
   *   Width and height of the video player.
   * @param bool $description
   *   Whether description is needed or not. Defaults to TRUE.
   *
   * @return array
   *   Resource details.
   */
  public function getResourceDetails($resource, $image_style = NULL, $image = TRUE, array $video_details = [], $description = TRUE) {
    $resource_data = [];
    // Fetch listing headline.
    if ($this->checkFieldExists($resource, 'field_headline') && $resource->field_headline->value) {
      $resource_data['title'] = $resource->field_headline->value ? $resource->field_headline->value : '';
    }
    // Fetch listing description.
    if ($this->checkFieldExists($resource, 'field_text') && $resource->field_text->value) {
      $listing_description = $resource->field_text->value ? $resource->field_text->processed : '';
    }
    // Fetching title when listing headline is not found.
    if (!isset($resource_data['title']) && empty($resource_data['title'])) {
      if ($this->checkFieldExists($resource, 'title') && $resource->title->value) {
        $resource_data['title'] = $resource->title->value ? $resource->title->value : '';
      }
    }
    if ($description) {
      // Fetching description when listing description is not found.
      if (!isset($listing_description) && empty($listing_description)) {
        if ($this->checkFieldExists($resource, 'body') && $resource->body->value) {
          $resource_data['description'] = $resource->body->value ? $resource->body->processed : '';
        }
      }
      else {
        $resource_data['description'] = $listing_description;
      }
    }
    $resource_node_id = $this->getId($resource);
    $resource_data['link'] = $this->generateUrlFromRoute($resource_node_id, 'node', $resource_data['title']);
    // Fetching image from listing image field.
    $listing_image = $this->buildImage($resource, 'field_image', $image_style, $resource_data['title']);
    // Check if image is requested and listing image is available then it will
    // be fetched on priority. $resource_data['image'] variable is used to
    // display image in components wherever the resource would be listed.
    if ($image) {
      $resource_data['image'] = $listing_image ? $listing_image : [];
    }
    // Fetch emphasis data.
    $emphasis_details = $this->getEmphasis($resource, 'field_main_emphasis', $image_style, $video_details);
    if ($emphasis_details) {
      switch (key($emphasis_details)) {
        case 'main_video':
          // Fetch video emphasis.
          $resource_data['video'] = $emphasis_details['main_video'];
          break;

        case 'gallery_images':
          // Fetch photo gallery emphasis.
          $gallery_images = $emphasis_details['gallery_images'];
          $resource_data['gallery_images'] = $gallery_images;
          // If image is requested and there is no listing image available then
          // fetch the first image from the photo gallery emphasis.
          if ($image) {
            $resource_data['image'] = empty($resource_data['image']) ? (isset($gallery_images[0]) ? $gallery_images[0] : []) : $resource_data['image'];
          }
          break;

        case 'main_image':
          // Fetch main image emphasis. If listing image is added then it will
          // be displayed instead of main image emphasis.
          $main_image = $listing_image ? $listing_image : $emphasis_details['main_image'];
          $resource_data['main_image'] = $main_image;
          // If image is requested and there is no listing image available then
          // display image from the main image emphasis.
          if ($image) {
            $resource_data['image'] = empty($resource_data['image']) ? $main_image : $resource_data['image'];
          }
          break;
      }
    }
    if ($this->checkFieldExists($resource, 'field_category') && $resource->field_category->entity) {

      // Fetch the translated referenced entities.
      $category = $this->getReferencedEntities($resource, 'field_category', TRUE);
      $category_name = $this->getTitle($category);
      $category_id = $this->getId($category);
      if ($category_name && $category_id) {
        $resource_data['category'] = $this->generateLinkFromTextAndUrl($category_name, $this->generateUrlFromRoute($category_id, 'taxonomy_term', $category_name));
      }
    }
    if ($this->checkFieldExists($resource, 'field_tags') && $resource->field_tags) {
      $tag_references = $this->getReferencedEntities($resource, 'field_tags');
      $resource_data['tags'] = [];
      foreach ($tag_references as $key => $tag_reference) {
        $tag_name[$key] = $this->getTitle($tag_reference);
        $tag_id[$key] = $this->getId($tag_reference);
        if ($tag_name[$key] && $tag_id[$key]) {
          $resource_data['tags'][] = $this->generateLinkFromTextAndUrl($tag_name[$key], $this->generateUrlFromRoute($tag_id[$key], 'taxonomy_term', $tag_name[$key]));
        }
      }
    }
    return $resource_data;
  }

  /**
   * Wrapper Function to Drupal native file_load.
   *
   * @param int $fid
   *   File ID to get information for.
   *
   * @return array
   *   Array of file information like
   *   - file_object: Drupal loaded file object.
   *   - file_url: Absolute File URL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function fileLoad($fid) {
    $fileInfo = [];
    if ($fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if (is_object($file)) {
        $fileInfo = [
          'file_object' => $file,
          'file_url' => file_create_url($file->getFileUri()),
        ];
      }
    }
    return $fileInfo;
  }

  /**
   * Fetch Emphasis details for given entity.
   *
   * @param object $entity
   *   Entity whose emphasis details need to be fetched.
   * @param string $emphasis_field
   *   Machine name of the emphasis field.
   * @param null|string $image_style
   *   Responsive image style.
   * @param array $video_details
   *   Width and height of the video player.
   *
   * @return array
   *   Returns emphasis details.
   */
  public function getEmphasis($entity, $emphasis_field, $image_style = NULL, array $video_details = []) {
    $emphasis_data = [];
    if ($this->checkValidEntity($entity, 'node') && $this->checkFieldExists($entity, $emphasis_field) && $entity->$emphasis_field->entity) {
      $emphasis_reference = $entity->$emphasis_field->entity;
      $emphasis_type = $this->getEntityType($emphasis_reference);
      switch ($emphasis_type) {
        case 'main_image':
          // Fetching image from emphasis main image.
          $main_image_details = $this->buildImage($emphasis_reference, 'field_image', $image_style, $entity->getTitle());
          $emphasis_data['main_image'] = $main_image_details ? $main_image_details : [];
          break;

        case 'photo_gallery':
          // Fetching gallery images from emphasis photo gallery.
          $gallery_details = $this->buildImage($emphasis_reference, 'field_gallery', $image_style, $entity->getTitle());
          $emphasis_data['gallery_images'] = $gallery_details ? $gallery_details : [];
          break;

        case 'main_video':
          // Fetching video from emphasis video.
          if ($this->checkFieldExists($emphasis_reference, 'field_video') && $emphasis_reference->field_video->value) {
            $width = isset($video_details['width']) ? $video_details['width'] : NULL;
            $height = isset($video_details['height']) ? $video_details['height'] : NULL;
            $emphasis_data['main_video'] = $this->getVideoPlayer($emphasis_reference->field_video->value, $width, $height);
          }
          break;
      }
    }
    return $emphasis_data;
  }

  /**
   * Fetch view data as per the offset provided.
   *
   * @param string $start_offset
   *   Starting offset.
   * @param string $end_offset
   *   Ending offset.
   * @param string $view_id
   *   View ID.
   * @param string $display_id
   *   Display ID.
   *
   * @return array
   *   Returns renderable view.
   */
  public function getRenderableView($start_offset, $end_offset, $view_id, $display_id) {
    $viewObject = $this->entityTypeManager->getStorage('view')->load($view_id);
    $view = $this->viewExecutable->get($viewObject);
    if (is_object($view)) {
      $view->setDisplay($display_id);

      switch ($display_id) {
        case 'auto_resource_list':
          $view = $this->getLimitedContentListFromViews($view, $start_offset, $end_offset);
          break;
      }
      $view->execute();
      $view_render = $view->buildRenderable($display_id);
      return $view_render;
    }
  }

  /**
   * Returns the view with filtered data according to offset provided.
   *
   * @param object $view
   *   View object.
   * @param string $start_offset
   *   Starting offset.
   * @param string $end_offset
   *   Ending offset.
   *
   * @return array
   *   Returns view.
   */
  public function getLimitedContentListFromViews($view, $start_offset, $end_offset) {
    if ($end_offset < 9) {
      $view->pager = new Some([], 'limited_pager', []);
      $view->pager->init($view, $view->display_handler);
      $view->setOffset($start_offset - 1);
      $view->setItemsPerPage(3);
    }
    return $view;
  }

  /**
   * Returns Vimeo/Youtube video player iframe.
   *
   * @param string $input_video_url
   *   Input Video Url. Supports both Vimeo as well as Youtube.
   * @param null|int $width
   *   Player width.
   * @param null|int $height
   *   Player height.
   *
   * @return array
   *   Returns Vimeo/Youtube video player iframe.
   */
  public function getVideoPlayer($input_video_url, $width = NULL, $height = NULL) {
    $video_player = [];
    $video_url = $provider = '';
    // Check if Vimeo video. Returns FALSE if not vimeo video.
    $vimeo_video_id = Vimeo::getIdFromInput($input_video_url);
    if ($vimeo_video_id) {
      $provider = 'vimeo';
      $video_url = sprintf('https://player.vimeo.com/video/%s', $vimeo_video_id);
    }
    // Check if Youtube video. Returns FALSE if not youtube video.
    $youtube_video_id = YouTube::getIdFromInput($input_video_url);
    if ($youtube_video_id) {
      $provider = 'youtube';
      $video_url = sprintf('https://www.youtube.com/embed/%s', $youtube_video_id);
    }
    // Default width and height of the player, if not passed.
    $width = $width ? $width : 1000;
    $height = $height ? $height : 800;
    // Build player.
    if ($video_url) {
      $video_player = [
        '#theme' => 'video_embed_iframe',
        '#provider' => $provider,
        '#url' => $video_url,
        '#attributes' => [
          'width' => $width,
          'height' => $height,
          'frameborder' => '0',
          'allowfullscreen' => 'allowfullscreen',
        ],
      ];
    }
    return $video_player;
  }

  /**
   * Custom function to fetch cache tags for the given entity.
   *
   * @param object $entity
   *   Entity whose cache tags need to be fetched.
   *
   * @return mixed
   *   Cache tags.
   */
  public function fetchEntityCacheTags($entity) {
    return $entity->getCacheTags();
  }

  /**
   * Custom function to fetch parent entity of the given entity.
   *
   * @param object $entity
   *   Entity whose parent needs to be fetched.
   *
   * @return mixed
   *   Parent Entity.
   */
  public function fetchParentEntity($entity) {
    return $entity->getParentEntity();
  }

  /**
   * Custom function to check if entity is of the given entity type.
   *
   * @param object $entity
   *   Entity.
   * @param string $entity_type
   *   Entity type.
   *
   * @return bool
   *   Returns TRUE/FALSE if the entity is of the given entity type.
   */
  public function checkValidEntity($entity, $entity_type) {
    switch ($entity_type) {
      case 'node':
        return $entity instanceof NodeInterface;

      case 'paragraph':
        return $entity instanceof ParagraphInterface;

      case 'media':
        return $entity instanceof Media;

      case 'term':
        return $entity instanceof Term;
    }
  }

  /**
   * Custom function to fetch link object.
   *
   * Supports internal and external urls.
   *
   * @param object $entity
   *   Entity to fetch link.
   * @param string $field_name
   *   Link field.
   *
   * @return mixed
   *   Returns user-entered url.
   */
  public function fetchLinkObject($entity, $field_name) {
    return $entity->$field_name->first()->getUrl();
  }


  /**
   * Wrapper function to fetch parameter from current route.
   *
   * @param string $parameter
   *   The parameter required from the route.
   *
   * @return mixed|null
   *   The entity object or value of the parameter requested from the route.
   */
  public function getRouteMatchParameter($parameter) {
    return $this->routeMatch->getParameter($parameter);
  }

  /**
   * Custom Plural formatter that formats a string based on the count.
   *
   * @param int $count
   *   Count of items.
   * @param string $singMsg
   *   The singular message. Displayed in case of count = 1.
   * @param string $plurMsg
   *   The plural message. Displayed in case of count > 1.
   *
   * @return \Drupal\Core\StringTranslation\PluralTranslatableMarkup
   *   Returns the formatted string.
   */
  public function customFormatPlural($count, $singMsg, $plurMsg) {
    return $this->translationManager->formatPlural($count, $singMsg, $plurMsg);
  }

  /**
   * Helper function to fetch designer details for the given author entity.
   *
   * @param object $author
   *   
   entity whose details need to be fetched.
   *
   * @return array
   *   Returns designer details.
   */
  public function getAuthorDetails($author) {

    $author_data = [];
    $title = '';
    // Fetch title.
    if ($this->checkFieldExists($author, 'title') && $author->title->value) {
      $title = $author->title->value;
      $author_data['name'] = $title;
    }

    if ($this->checkFieldExists($author, 'body') && $author->body->value) {
      $author_data['description'] = $author->body->processed;
    }

    // Get the entity profile image from listing.
    if ($this->checkFieldExists($author, 'field_image') && $author->field_image->entity) {
      $author_data['image'] = $this->buildImage($author, 'field_image', 'content_listing_highlight_list_image', $title);
    }

    // If listing image is not there, get profile image for listing.
    if (empty($author_data['image'])) {
      if ($this->checkFieldExists($author, 'field_profile_image') && $author->field_profile_image->entity) {
        $author_data['image'] = $this->buildImage($author, 'field_profile_image', 'content_listing_highlight_list_image', $title);
      }
    }

    // Fetch content node detail link.
    $author_data['link'] = $this->generateUrlFromRoute($this->getId($author), 'node', $title);
    return $author_data;
  }

}
