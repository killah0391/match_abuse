<?php

namespace Drupal\match_abuse;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Match Abuse Report entity.
 */
class MatchAbuseReportAccessControlHandler extends EntityAccessControlHandler
{

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account)
  {
    /** @var \Drupal\match_abuse\Entity\MatchAbuseReport $entity */

    // Admin permission check (supersedes most other checks)
    if ($account->hasPermission('administer abuse reports')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
        // Allow reporter to view their own reports.
        return AccessResult::allowedIf($entity->get('reporter_uid')->target_id == $account->id())
          ->cachePerUser()->addCacheableDependency($entity);

      case 'update': // Covers 'edit' and our custom 'user_reply' if not routed separately
      case 'user_reply': // A custom operation check if needed
        // Allow reporter to edit/reply ONLY if status is 'waiting_user'.
        return AccessResult::allowedIf(
          $entity->get('reporter_uid')->target_id == $account->id() &&
            $entity->get('status')->value == 'waiting_user'
        )->cachePerUser()->addCacheableDependency($entity);

      case 'delete':
        // Only admins (checked above) can delete.
        return AccessResult::forbidden();
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL)
  {
    // Anyone with 'report abuse' permission can create.
    return AccessResult::allowedIfHasPermission($account, 'report abuse');
  }
}
