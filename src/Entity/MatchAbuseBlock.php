<?php

namespace Drupal\match_abuse\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the Match Abuse Block entity.
 *
 * @ContentEntityType(
 * id = "match_abuse_block",
 * label = @Translation("Match Abuse Block"),
 * base_table = "match_abuse_block",
 * entity_keys = {
 * "id" = "id",
 * "uuid" = "uuid",
 * "blocker" = "blocker_uid",
 * "blocked" = "blocked_uid",
 * },
 * )
 */
class MatchAbuseBlock extends ContentEntityBase
{

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
  {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Match Abuse Block entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Match Abuse Block entity.'))
      ->setReadOnly(TRUE);

    $fields['blocker_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Blocker User'))
      ->setDescription(t('The user who initiated the block.'))
      ->setSetting('target_type', 'user');

    $fields['blocked_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Blocked User'))
      ->setDescription(t('The user who is being blocked.'))
      ->setSetting('target_type', 'user');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    return $fields;
  }
}
