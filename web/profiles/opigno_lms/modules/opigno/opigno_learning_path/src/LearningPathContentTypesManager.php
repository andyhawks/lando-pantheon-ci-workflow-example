<?php

namespace Drupal\opigno_learning_path;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\opigno_learning_path\Annotation\LearningPathContentType;

class LearningPathContentTypesManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler)
  {
    // construct the object. It needs:
    // - The subdir where the plugin must be implemented in other modules.
    // - The namespace
    // - The module handler
    // - The interface the implementation must implements. (In this case, the implementation must extends the class ContentTypeBase)
    // - The annotation class.
    parent::__construct(
      'Plugin/LearningPathContentType',
      $namespaces,
      $module_handler,
      'Drupal\opigno_learning_path\ContentTypeInterface',
      'Drupal\opigno_learning_path\Annotation\LearningPathContentType'
    );

    // Always useful to add an alter hook available.
    $this->alterInfo('learning_path_content_type_alter');

    // Set a cache to speed up the retrieving process.
    $this->setCacheBackend($cache_backend, 'learning_path_content_types');
  }

  /**
   * @param $plugin_id
   * @param array $configuration
   * @return object|ContentTypeBase
   */
  public function createInstance($plugin_id, array $configuration = [])
  {
    return parent::createInstance($plugin_id, $configuration);
  }

}
