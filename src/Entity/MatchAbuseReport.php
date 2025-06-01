<?php

namespace Drupal\match_abuse\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityInterface; // Add this
use Drupal\Core\Entity\EntityStorageInterface; // Add this

/**
 * Defines the Match Abuse Report entity.
 *
 * @ContentEntityType(
 * id = "match_abuse_report",
 * label = @Translation("Match Abuse Report"),
 * handlers = {
 * "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 * "list_builder" = "Drupal\match_abuse\MatchAbuseReportListBuilder",
 * "views_data" = "Drupal\views\EntityViewsData",
 * "form" = {
 * "default" = "Drupal\Core\Entity\ContentEntityForm",
 * "add" = "Drupal\match_abuse\Form\MatchAbuseReportForm",
 * "edit" = "Drupal\Core\Entity\ContentEntityForm",
 * "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 * "user_reply" = "Drupal\match_abuse\Form\MatchAbuseReportUserReplyForm",
 * },
 * "route_provider" = {
 * "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 * },
 * "access" = "Drupal\match_abuse\MatchAbuseReportAccessControlHandler",
 * },
 * base_table = "match_abuse_report",
 * admin_permission = "administer abuse reports",
 * entity_keys = {
 * "id" = "id",
 * "uuid" = "uuid",
 * "label" = "reason",
 * "reporter" = "reporter_uid",
 * "reported" = "reported_uid",
 * },
 * links = {
 * "canonical" = "/admin/content/abuse-report/{match_abuse_report}",
 * "add-form" = "/user/{user_to_report}/report-abuse",
 * "edit-form" = "/admin/content/abuse-report/{match_abuse_report}/edit",
 * "delete-form" = "/admin/content/abuse-report/{match_abuse_report}/delete",
 * "collection" = "/admin/content/abuse-reports",
 * "user-reply-form" = "/my-abuse-report/{match_abuse_report}/reply",
 * },
 * fieldable = TRUE,
 * )
 */
class MatchAbuseReport extends ContentEntityBase implements ContentEntityInterface
{

  // baseFieldDefinitions method remains mostly the same,
  // but ensure fields are display-configurable if needed.
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
  {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Match Abuse Report entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Match Abuse Report entity.'))
      ->setReadOnly(TRUE);

    $fields['reporter_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Reporter User'))
      ->setDescription(t('The user who submitted the report.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['reported_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Reported User'))
      ->setDescription(t('The user who is being reported.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);


    $fields['reason'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reason'))
      ->setDescription(t('The reason for the abuse report.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['message'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Message'))
      ->setDescription(t('A detailed message explaining the abuse.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The status of the abuse report.'))
      ->setSettings([
        'allowed_values' => [
          'new' => 'New',
          'reviewed' => 'Reviewed',
          'waiting_user' => 'Waiting for User Reply', // Added
          'user_replied' => 'User Replied',
          'resolved' => 'Resolved',
        ],
      ])
      ->setDefaultValue('new')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['admin_notes'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Admin Notes / Answers'))
      ->setDescription(t('Internal notes or answers from administrators regarding this report.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED) // Allow multiple notes
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['user_notes'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Your Notes / Replies'))
      ->setDescription(t('Your replies to administrator notes.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 11,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 11,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);


    return $fields;
  }
}
