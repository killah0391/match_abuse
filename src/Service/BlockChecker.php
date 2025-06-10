<?php

namespace Drupal\match_abuse\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for checking block status between users.
 */
class BlockChecker implements BlockCheckerInterface
{

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new BlockChecker object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager)
  {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks if user_a has blocked user_b.
   *
   * @param \Drupal\Core\Session\AccountInterface $user_a
   *   The potential blocker.
   * @param \Drupal\Core\Session\AccountInterface $user_b
   *   The potential blocked user.
   *
   * @return bool
   *   TRUE if user_a has blocked user_b, FALSE otherwise.
   */
  private function userHasBlocked(AccountInterface $user_a, AccountInterface $user_b): bool
  {
    if ($user_a->id() == $user_b->id()) {
      return FALSE; // Users cannot block themselves.
    }
    $block_storage = $this->entityTypeManager->getStorage('match_abuse_block');
    $query = $block_storage->getQuery()
      ->condition('blocker_uid', $user_a->id())
      ->condition('blocked_uid', $user_b->id())
      ->accessCheck(TRUE)
      ->range(0, 1); // We only need to know if at least one exists.
    $ids = $query->execute();

    return !empty($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function isBlockActive(AccountInterface $user_one, AccountInterface $user_two): bool
  {
    if ($user_one->id() == $user_two->id()) {
      return FALSE;
    }
    return $this->userHasBlocked($user_one, $user_two) || $this->userHasBlocked($user_two, $user_one);
  }

  /**
   * {@inheritdoc}
   */
  public function isUserBlockedBy(AccountInterface $blocked_user, AccountInterface $blocker_user): bool {
    // This directly uses the private helper method.
    return $this->userHasBlocked($blocker_user, $blocked_user);
  }
}
