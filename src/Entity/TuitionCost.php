<?php

namespace Drupal\du_tuition_calculator\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a the TuitionCost entity.
 *
 * @ContentEntityType(
 *   id = "du_tuition",
 *   label = @Translation("Tuition Cost"),
 *   admin_permission = "administer DU tuition calculator",
 *   base_table = "du_tuition",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 * )
 */
class TuitionCost extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the tuition cost entity.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('A boolean indicating whether the tuition cost is enabled.'))
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

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the tuition cost was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the tuition cost was last edited.'));

    $fields['duid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('DU ID'))
      ->setDescription(t('The ID of the tuition cost entity passed from DU.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['academic_year'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Academic Year'))
      ->setDescription(t('The academic year of the tuition cost entity.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['per_credit'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Cost per Credit'))
      ->setDescription(t('The per credit cost of the tuition cost entity.'))
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

    $fields['average_credits'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Average Credits Per Year'))
      ->setDescription(t('The average credits per year in this program.'))
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

    $fields['updated_du'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Last Updated'))
      ->setDescription(t('The last known update in the DU system.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    // String fields.
    $string_fields = [
      'academic_term' => [
        'title' => t('Academic Term'),
        'required' => FALSE,
      ],
      'academic_term_code' => [
        'title' => t('Academic Term Code'),
        'required' => FALSE,
      ],
      'cohort_code' => [
        'title' => t('Cohort Code'),
        'required' => FALSE,
      ],
      'college' => [
        'title' => t('College'),
        'required' => TRUE,
      ],
      'college_code' => [
        'title' => t('College Code'),
        'required' => TRUE,
      ],
      'department' => [
        'title' => t('Department'),
        'required' => TRUE,
      ],
      'department_code' => [
        'title' => t('Department Code'),
        'required' => TRUE,
      ],
      'degree' => [
        'title' => t('Degree'),
        'required' => TRUE,
      ],
      'degree_code' => [
        'title' => t('Degree Code'),
        'required' => TRUE,
      ],
      'detail_code' => [
        'title' => t('Detail Code'),
        'required' => FALSE,
      ],
      'level' => [
        'title' => t('Level'),
        'required' => TRUE,
      ],
      'level_code' => [
        'title' => t('Level Code'),
        'required' => TRUE,
      ],
      'major' => [
        'title' => t('Major'),
        'required' => TRUE,
      ],
      'major_code' => [
        'title' => t('Major Code'),
        'required' => TRUE,
      ],
      'program' => [
        'title' => t('Program'),
        'required' => TRUE,
      ],
      'program_code' => [
        'title' => t('Program Code'),
        'required' => TRUE,
      ],
    ];

    foreach ($string_fields as $string_machine => $string_field) {
      $fields[$string_machine] = BaseFieldDefinition::create('string')
        ->setLabel($string_field['title'])
        ->setDescription(t('The %field of the tuition cost entity.', ['%field' => $string_field['title']]))
        ->setRequired($string_field['required'])
        ->setSetting('max_length', 255)
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
          'weight' => -5,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayOptions('view', [
          'label' => 'inline',
          'type' => 'string',
          'weight' => -5,
        ])
        ->setDisplayConfigurable('view', TRUE);
    }

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

    return $fields;
  }

}
