<?php

namespace Drupal\match_abuse\Service;
use Drupal\Core\Session\AccountInterface;

/**
 * Interface for checking block status between users.
 */
interface BlockCheckerInterface
{

  /**
   * Checks if there is any block active between two users.
   *
   * This means either user_one has blocked user_two OR user_two has blocked user_one.
   *
   * @param \Drupal\Core\Session\AccountInterface $user_one
   *   The first user (e.g., current user).
   * @param \Drupal\Core\Session\AccountInterface $user_two
   *   The second user (e.g., profile being viewed).
   *
   * @return bool
   *   TRUE if a block is active in either direction, FALSE otherwise.
   */
  public function isBlockActive(AccountInterface $user_one, AccountInterface $user_two): bool;

  /**
   * Checks if a specific user is blocked by another specific user.
   *
   * @param \Drupal\Core\Session\AccountInterface $blocked_user
   *   The user to check if they are blocked.
   * @param \Drupal\Core\Session\AccountInterface $blocker_user
   *   The user who might be doing the blocking.
   *
   * @return bool
   *   TRUE if $blocker_user has blocked $blocked_user, FALSE otherwise.
   */
  public function isUserBlockedBy(AccountInterface $blocked_user, AccountInterface $blocker_user): bool;
}
