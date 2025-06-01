<?php

namespace Drupal\match_abuse\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;
use Drupal\user\Entity\User;

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

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Only act on new block creations (when $update is FALSE).
    if (!$update) {
      $blocker_uid_field = $this->get('blocker_uid');
      $blocked_uid_field = $this->get('blocked_uid');

      // Ensure the fields and their target_id properties exist.
      if (!$blocker_uid_field->isEmpty() && !$blocked_uid_field->isEmpty()) {
        $blocker_uid = $blocker_uid_field->target_id;
        $blocked_uid = $blocked_uid_field->target_id;

        $blocker_user = User::load($blocker_uid);
        $blocked_user = User::load($blocked_uid);

        if ($blocker_user && $blocked_user) {
          // Action 1: Remove blocker from blocked_user's private gallery
          self::revokePrivateGalleryAccess($blocked_user, $blocker_user);

          // Action 2: Remove blocked_user from blocker's private gallery
          self::revokePrivateGalleryAccess($blocker_user, $blocked_user);
        }
      }
    }
  }

  /**
   * Helper function to remove a user from another user's private gallery allowed list.
   *
   * @param \Drupal\user\UserInterface $gallery_owner
   *   The owner of the private gallery.
   * @param \Drupal\user\UserInterface $user_to_remove
   *   The user to remove from the allowed list.
   */
  protected static function revokePrivateGalleryAccess(UserInterface $gallery_owner, UserInterface $user_to_remove) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $gallery_storage = $entity_type_manager->getStorage('gallery');
    $logger = \Drupal::logger('match_abuse');

    $galleries = $gallery_storage->loadByProperties([
      'uid' => $gallery_owner->id(),
      'gallery_type' => 'private',
    ]);

    if (!empty($galleries)) {
      /** @var \Drupal\user_galleries\Entity\Gallery $gallery */
      $gallery = reset($galleries);
      $allowed_users_field = $gallery->get('allowed_users');

      $current_allowed_ids = [];
      foreach ($allowed_users_field as $item) {
        if (isset($item->target_id)) {
          $current_allowed_ids[] = $item->target_id;
        }
      }

      $user_to_remove_id = $user_to_remove->id();
      if (in_array($user_to_remove_id, $current_allowed_ids)) {
        $new_allowed_values = array_filter($current_allowed_ids, function ($uid) use ($user_to_remove_id) {
          return $uid != $user_to_remove_id;
        });
        $gallery->set('allowed_users', array_map(function($uid) { return ['target_id' => $uid]; }, $new_allowed_values));
        $gallery->save();
        $logger->info('User @removed_user_id access revoked from private gallery (ID: @gallery_id) of user @gallery_owner_id due to a block action.', [
          '@removed_user_id' => $user_to_remove_id,
          '@gallery_id' => $gallery->id(),
          '@gallery_owner_id' => $gallery_owner->id(),
        ]);
      }
    }
  }
}
