<?php

namespace Drupal\match_abuse\Controller;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\MessageCommand;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MatchAbuseController extends ControllerBase
{

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new MatchAbuseController object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * The renderer.
   */
  public function __construct(RendererInterface $renderer)
  {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * Generates the dropdown items for block/unblock and report actions.
   *
   * @param \Drupal\user\UserInterface $user_to_check
   * The user whose profile is being viewed.
   *
   * @return array
   * An array of link items suitable for #theme => 'links'.
   */
  private function getDropdownItems(UserInterface $user_to_check): array
  {
    $current_user = $this->currentUser();
    $dropdown_items = [];

    // Determine if the current user has blocked user_to_check.
    $is_blocked = FALSE;
    if ($current_user->id() != $user_to_check->id()) {
      $storage = $this->entityTypeManager()->getStorage('match_abuse_block');
      $query = $storage->getQuery()
        ->condition('blocker_uid', $current_user->id())
        ->condition('blocked_uid', $user_to_check->id())
        ->accessCheck(TRUE);
      $ids = $query->execute();
      $is_blocked = !empty($ids);
    }

    if ($current_user->id() != $user_to_check->id() && $current_user->hasPermission('block users')) {
      if ($is_blocked) {
        $dropdown_items['unblock_user'] = [
          'title' => $this->t('Unblock User'),
          'url' => Url::fromRoute('match_abuse.ajax_unblock_user', ['user_to_unblock' => $user_to_check->id()]),
          'attributes' => ['class' => ['use-ajax', 'dropdown-item', 'match-abuse-link']],
        ];
      } else {
        $dropdown_items['block_user'] = [
          'title' => $this->t('Block User'),
          'url' => Url::fromRoute('match_abuse.ajax_block_user', ['user_to_block' => $user_to_check->id()]),
          'attributes' => ['class' => ['use-ajax', 'dropdown-item', 'match-abuse-link']],
        ];
      }
    }

    if ($is_blocked && $current_user->hasPermission('report abuse')) {
      $dropdown_items['report_abuse'] = [
        'title' => $this->t('Report Abuse'),
        'url' => Url::fromRoute('match_abuse.report_abuse', ['user_to_report' => $user_to_check->id()]),
        'attributes' => ['class' => ['dropdown-item', 'match-abuse-link']],
      ];
    }
    return $dropdown_items;
  }

  /**
   * Generates render arrays for profile alert and main content based on block status.
   *
   * @param \Drupal\user\UserInterface $user_being_viewed
   *   The user whose profile is being viewed.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current logged-in user.
   *
   * @return array
   *   An array containing 'alert_content' and 'main_content' render arrays.
   */
  private function getProfileDisplayElements(UserInterface $user_being_viewed, AccountInterface $current_user): array
  {
    $viewed_user_id = $user_being_viewed->id();
    $current_user_id = $current_user->id();
    $block_storage = $this->entityTypeManager()->getStorage('match_abuse_block');

    $current_user_has_blocked_viewed_user = !empty($block_storage->getQuery()
      ->condition('blocker_uid', $current_user_id)
      ->condition('blocked_uid', $viewed_user_id)
      ->accessCheck(TRUE)
      ->execute());

    $viewed_user_has_blocked_current_user = !empty($block_storage->getQuery()
      ->condition('blocker_uid', $viewed_user_id)
      ->condition('blocked_uid', $current_user_id)
      ->accessCheck(TRUE)
      ->execute());

    $alert_render_array = ['#markup' => ''];
    $main_content_render_array = ['#markup' => '']; // Default to empty if content should be hidden.

    if ($viewed_user_has_blocked_current_user) {
      $alert_render_array = [
        '#type' => 'markup',
        '#markup' => '<div class="alert alert-warning mt-3" role="alert">' . $this->t('%username has blocked you.', ['%username' => $user_being_viewed->getAccountName()]) . '</div>',
      ];
    } elseif ($current_user_has_blocked_viewed_user) {
      $report_button_link = '';
      if ($current_user->hasPermission('report abuse')) {
        $report_button_link = Link::createFromRoute(
          $this->t('Report @username', ['@username' => $user_being_viewed->getAccountName()]),
          'match_abuse.report_abuse',
          ['user_to_report' => $viewed_user_id],
          ['attributes' => ['class' => ['btn', 'btn-danger', 'btn-sm', 'ms-2']]]
        )->toString();
      }
      $alert_render_array = [
        '#type' => 'markup',
        '#markup' => '<div class="alert alert-warning mt-3" role="alert">' . $this->t('You have blocked %username.', ['%username' => $user_being_viewed->getAccountName()]) . ' ' . $report_button_link . '</div>',
      ];
    } else {
      // No block: main content should be the user's profile fields.
      // Load the 'default' entity view display for users.
      $display = $this->entityTypeManager()
        ->getStorage('entity_view_display')
        ->load('user.user.default');

      if ($display) {
        $full_content_build = $display->build($user_being_viewed);
        // These elements are handled by Twig's `without` or other AJAX commands.
        unset($full_content_build['user_picture']);
        unset($full_content_build['match_abuse_user_actions']);
        unset($full_content_build['match_abuse_block_alert']);
        $main_content_render_array = $full_content_build;
      } else {
        $main_content_render_array = ['#markup' => $this->t('Error: Could not load user display configuration.')];
      }
    }

    return [
      'alert_content' => $alert_render_array,
      'main_content' => $main_content_render_array,
    ];
  }

  /**
   * Handles AJAX request to block a user.
   *
   * @param \Drupal\user\UserInterface $user_to_block
   * The user to block.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * The AJAX response.
   */
  public function ajaxBlockUser(UserInterface $user_to_block): AjaxResponse
  {
    $current_user = $this->currentUser();
    $response = new AjaxResponse();

    // Check if already blocked (to prevent duplicates)
    $storage = $this->entityTypeManager()->getStorage('match_abuse_block');
    $query = $storage->getQuery()
      ->condition('blocker_uid', $current_user->id())
      ->condition('blocked_uid', $user_to_block->id())
      ->accessCheck(TRUE);
    $ids = $query->execute();

    if (empty($ids)) {
      $block = $storage->create([
        'blocker_uid' => $current_user->id(),
        'blocked_uid' => $user_to_block->id(),
      ]);
      $block->save();
      $message = $this->t('You have blocked %username!', ['%username' => $user_to_block->getAccountName()]);
      $response->addCommand(new MessageCommand($message, NULL, ['type' => 'status']));
    } else {
      $message = $this->t('%username is already blocked!', ['%username' => $user_to_block->getAccountName()]);
      $response->addCommand(new MessageCommand($message, NULL, ['type' => 'error']));
    }

    // Replace the dropdown menu content.
    $dropdown_items = $this->getDropdownItems($user_to_block);
    $dropdown_menu_id_selector = '#userActionsDropdownMenu-' . $user_to_block->id();
    $new_menu_render_array = [
      '#theme' => 'links',
      '#links' => $dropdown_items,
      '#attributes' => [
        'class' => ['dropdown-menu'],
        'aria-labelledby' => 'userActionsDropdown-' . $user_to_block->id(),
      ],
    ];
    $response->addCommand(new ReplaceCommand($dropdown_menu_id_selector, $new_menu_render_array));

    // After replacing content, tell Bootstrap to show the dropdown again.
    $response->addCommand(new InvokeCommand('#userActionsDropdown-' . $user_to_block->id(), 'dropdown', ['show']));

    // Update profile alert and main content area.
    $profile_elements = $this->getProfileDisplayElements($user_to_block, $current_user);
    $alert_wrapper_selector = '#profile-alert-area-' . $user_to_block->id();
    $response->addCommand(new ReplaceCommand($alert_wrapper_selector, $profile_elements['alert_content']));

    $main_content_wrapper_selector = '#profile-main-content-area-' . $user_to_block->id();
    $response->addCommand(new ReplaceCommand($main_content_wrapper_selector, $profile_elements['main_content']));

    return $response;
  }

  /**
   * Handles AJAX request to unblock a user.
   *
   * @param \Drupal\user\UserInterface $user_to_unblock
   * The user to unblock.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * The AJAX response.
   */
  public function ajaxUnblockUser(UserInterface $user_to_unblock): AjaxResponse
  {
    $current_user = $this->currentUser();
    $response = new AjaxResponse();

    $storage = $this->entityTypeManager()->getStorage('match_abuse_block');
    $query = $storage->getQuery()
      ->condition('blocker_uid', $current_user->id())
      ->condition('blocked_uid', $user_to_unblock->id())
      ->accessCheck(TRUE);
    $ids = $query->execute();

    if (!empty($ids)) {
      $entities = $storage->loadMultiple($ids);
      $storage->delete($entities);
      $message = $this->t('You have unblocked %username!', ['%username' => $user_to_unblock->getAccountName()]);
      $response->addCommand(new MessageCommand($message, NULL, ['type' => 'status']));
    } else {
      $message = $this->t('%username was not blocked!', ['%username' => $user_to_unblock->getAccountName()]);
      $response->addCommand(new MessageCommand($message, NULL, ['type' => 'error']));
    }

    // Replace the dropdown menu content.
    $dropdown_items = $this->getDropdownItems($user_to_unblock);
    $dropdown_menu_id_selector = '#userActionsDropdownMenu-' . $user_to_unblock->id();
    $new_menu_render_array = [
      '#theme' => 'links',
      '#links' => $dropdown_items,
      '#attributes' => [
        'class' => ['dropdown-menu'],
        'aria-labelledby' => 'userActionsDropdown-' . $user_to_unblock->id(),
      ],
    ];
    $response->addCommand(new ReplaceCommand($dropdown_menu_id_selector, $new_menu_render_array));

    // After replacing content, tell Bootstrap to show the dropdown again.
    $response->addCommand(new InvokeCommand('#userActionsDropdown-' . $user_to_unblock->id(), 'dropdown', ['show']));

    // Update profile alert and main content area.
    $profile_elements = $this->getProfileDisplayElements($user_to_unblock, $current_user);
    $alert_wrapper_selector = '#profile-alert-area-' . $user_to_unblock->id();
    $response->addCommand(new ReplaceCommand($alert_wrapper_selector, $profile_elements['alert_content']));

    $main_content_wrapper_selector = '#profile-main-content-area-' . $user_to_unblock->id();
    $response->addCommand(new ReplaceCommand($main_content_wrapper_selector, $profile_elements['main_content']));

    return $response;
  }

  /**
   * Original non-AJAX unblock function (can be removed if not needed).
   */
  public function unblockUser($user_to_unblock)
  {
    // ... (Keep or remove original code) ...
    // Redirect back to user profile
    return new RedirectResponse(Url::fromRoute('entity.user.canonical', ['user' => $user_to_unblock])->toString());
  }
}
