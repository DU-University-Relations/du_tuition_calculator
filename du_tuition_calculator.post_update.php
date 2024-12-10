<?php

/**
 * @file
 * Defines post update routines for DU Tuition Calculator.
 */

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Implements hook_post_update_NAME().
 *
 * Update tuition entity with latest requirement changes.
 */
function du_tuition_calculator_post_update_8001_tuition_update(&$sandbox) {
  $update_manager = \Drupal::entityDefinitionUpdateManager();

  $entity = $update_manager->getEntityType('du_tuition');

  $attribute = $update_manager->getFieldStorageDefinition('attribute', 'du_tuition');
  $update_manager->uninstallFieldStorageDefinition($attribute);

  $attribute_code = $update_manager->getFieldStorageDefinition('attribute_code', 'du_tuition');
  $update_manager->uninstallFieldStorageDefinition($attribute_code);

  $fields['amount_per_term'] = BaseFieldDefinition::create('float')
    ->setLabel(t('Amount per Term'))
    ->setDescription(t('The per term cost of the tuition cost entity.'))
    ->setRequired(TRUE)
    ->setDisplayOptions('form', [
      'type' => 'number',
      'weight' => 0,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayOptions('view', [
      'label' => 'hidden',
      'type' => 'number_decimal',
      'weight' => 0,
      'settings' => [
        'thousand_separator' => ',',
        'decimal_separator' => '.',
        'scale' => 2,
        'prefix_suffix' => TRUE,
      ],
    ])
    ->setDisplayConfigurable('view', TRUE);

  $fields['billed_per_term'] = BaseFieldDefinition::create('boolean')
    ->setLabel(t('Billed per term?'))
    ->setDescription(t('A boolean indicating cost is billed per term.'))
    ->setDefaultValue(TRUE)
    ->setSetting('on_label', 'Enabled')
    ->setDisplayOptions('form', [
      'type' => 'boolean_checkbox',
      'settings' => [
        'display_label' => FALSE,
      ],
      'weight' => 0,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayOptions('view', [
      'type' => 'boolean',
      'label' => 'above',
      'weight' => 0,
      'settings' => [
        'format' => 'enabled-disabled',
      ],
    ])
    ->setDisplayConfigurable('view', TRUE);

  $fields['flat_rate'] = BaseFieldDefinition::create('boolean')
    ->setLabel(t('Flat rate?'))
    ->setDescription(t('A boolean indicating flat rate.'))
    ->setDefaultValue(TRUE)
    ->setSetting('on_label', 'Enabled')
    ->setDisplayOptions('form', [
      'type' => 'boolean_checkbox',
      'settings' => [
        'display_label' => FALSE,
      ],
      'weight' => 0,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayOptions('view', [
      'type' => 'boolean',
      'label' => 'above',
      'weight' => 0,
      'settings' => [
        'format' => 'enabled-disabled',
      ],
    ])
    ->setDisplayConfigurable('view', TRUE);

  foreach ($fields as $name => $field) {
    $update_manager->installFieldStorageDefinition($name, 'du_tuition', 'du_tuition', $field);
  }

  return t('The tuittion cost entity has been updated.');

}
