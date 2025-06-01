<?php

namespace Drupal\match_abuse\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\match_abuse\Ajax\ShowBootstrapToastCommand;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;
use Drupal\match_chat\Form\MatchMessageForm; // Added for type hinting and instantiation
use Symfony\Component\HttpFoundation\Request; // <-- Add this
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
   * Generates the block/unblock link container render array.
   *
   * @param \Drupal\user\UserInterface $user_to_check
   *   The user whose profile is being viewed or interacted with.
   * @param string|null $chat_thread_id
   *   Optional chat thread ID to include in AJAX URLs.
   * @param array $custom_options
   *   Optional array to customize classes and markup.
   *   - wrapper_classes: Array of classes for the wrapper container.
   *   - link_classes_base: Array of common classes for the link.
   *   - link_classes_block_state: Array of classes specific to the "block" state.
   *   - link_classes_unblock_state: Array of classes specific to the "unblock" state.
   *   - icon_markup: String HTML for the icon (e.g., Bootstrap icon).
   *
   * @return array
   *   A render array for the container holding the block/unblock link.
   */
  public function getBlockLinkRenderArray(UserInterface $user_to_check, ?string $chat_thread_id = NULL, array $custom_options = []): array
  {
    $current_user = $this->currentUser();

    if ($current_user->id() == $user_to_check->id() || !$current_user->hasPermission('block users')) {
      return []; // No action possible
    }

    $storage = $this->entityTypeManager()->getStorage('match_abuse_block');
    $query = $storage->getQuery()
      ->condition('blocker_uid', $current_user->id())
      ->condition('blocked_uid', $user_to_check->id())
      ->accessCheck(TRUE);
    $ids = $query->execute();

    $default_options = [
      'wrapper_classes' => [], // No specific wrapper classes by default
      'link_classes_base' => ['use-ajax', 'match-abuse-link', 'btn', 'd-block', 'w-100'], // Default to a full-width button style
      'link_classes_block_state' => ['btn-danger'], // Default state: makes it a red button
      'link_classes_unblock_state' => ['btn-success'], // Default state: makes it a green button
      'icon_markup' => '<i class="bi bi-person-slash" aria-hidden="true"></i> ', // Default icon
    ];
    $options = array_merge($default_options, $custom_options);

    $wrapper_id_attribute = 'match-abuse-block-link-wrapper-' . $user_to_check->id();
    $url_options = [];
    if ($chat_thread_id) {
      $url_options['query'] = ['chat_thread_id' => $chat_thread_id];
    }

    if (empty($ids)) {
      // Link to block
      $title_text = $this->t('Block @username', ['@username' => $user_to_check->getAccountName()]);
      $url = Url::fromRoute('match_abuse.ajax_block_user', ['user_to_block' => $user_to_check->id()], $url_options);
      $link_attributes = array_merge($options['link_classes_base'], $options['link_classes_block_state']);
    } else {
      // Link to unblock
      $title_text = $this->t('Unblock @username', ['@username' => $user_to_check->getAccountName()]);
      $url = Url::fromRoute('match_abuse.ajax_unblock_user', ['user_to_unblock' => $user_to_check->id()], $url_options);
      $link_attributes = array_merge($options['link_classes_base'], $options['link_classes_unblock_state']);
    }

    // This render array must match the structure defined in ChatSettingsPopoverForm
    // for the 'block_user_action_wrapper' so ReplaceCommand works as expected.
      return [
        '#type' => 'container',
        '#attributes' => ['id' => $wrapper_id_attribute, 'class' => $options['wrapper_classes']],
        'block_button_link' => [ // Key must match the one in ChatSettingsPopoverForm
          '#type' => 'link',
          '#title' => Markup::create($options['icon_markup'] . htmlspecialchars($title_text)),
          '#url' => $url,
          '#attributes' => [
            'class' => $link_attributes,
            'role' => 'button',
            'aria-label' => $title_text, // Full text for accessibility
          ],
        ],
      ];
  }

  /**
   * Handles AJAX request to block a user.
   *
   * @param \Drupal\user\UserInterface $user_to_block
   * The user to block.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * The AJAX response.
   */
  public function ajaxBlockUser(UserInterface $user_to_block, Request $request): AjaxResponse
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
      // --- USE NEW COMMAND ---
      $response->addCommand(new ShowBootstrapToastCommand($message, $this->t('User Blocked'), 'warning'));
    } else {
      $message = $this->t('%username is already blocked!', ['%username' => $user_to_block->getAccountName()]);
      // --- USE NEW COMMAND ---
      $response->addCommand(new ShowBootstrapToastCommand($message, $this->t('Already Blocked'), 'error'));
    }

    // Replace the link
    $chat_thread_id = $request->query->get('chat_thread_id');
    $options_for_link_render = [];

    if ($chat_thread_id) {
        // Options for chat popover, as per your request
        $options_for_link_render = [
            'wrapper_classes' => ['mt-3', 'mb-n4', 'mx-n4', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
            'link_classes_base' => ['use-ajax', 'match-abuse-link', 'btn', 'd-block', 'w-100', 'rounded-top-0'],
            'link_classes_block_state' => ['btn-danger', 'text-muted'],
            'link_classes_unblock_state' => ['btn-success', 'text-muted'],
            // Default icon will be used unless overridden here
        ];
    } else {
        // Options for profile page dropdown (or other non-chat contexts)
        $options_for_link_render = [
            'wrapper_classes' => [], // No extra margin for dropdown item wrapper.
            'link_classes_base' => ['use-ajax', 'match-abuse-link', 'dropdown-item'],
            'link_classes_block_state' => ['bg-danger', 'mb-n2', 'text-muted', 'rounded-bottom-2'], // Keep text-only danger for dropdown
            'link_classes_unblock_state' => ['bg-success', 'mb-n2', 'text-muted', 'rounded-bottom-2'], // Keep text-only success for dropdown
        ];
    }
    $link_container_render_array = $this->getBlockLinkRenderArray($user_to_block, $chat_thread_id, $options_for_link_render);
    $wrapper_selector = '#match-abuse-block-link-wrapper-' . $user_to_block->id();
    $response->addCommand(new ReplaceCommand($wrapper_selector, $link_container_render_array));

    // If a chat_thread_id is provided in the request, rebuild and replace the message form.
    if ($chat_thread_id) {
      $thread_storage = $this->entityTypeManager()->getStorage('match_thread');
      /** @var \Drupal\match_chat\Entity\MatchThreadInterface|null $thread_entity */
      $thread_entity = $thread_storage->load($chat_thread_id);
      if ($thread_entity) {
        // MatchMessageForm::class will resolve to the correct form class.
        $message_form_render_array = $this->formBuilder()->getForm(MatchMessageForm::class, $thread_entity);
        $message_form_wrapper_id = '#match-message-form-wrapper-' . $thread_entity->id();
        $response->addCommand(new ReplaceCommand($message_form_wrapper_id, $message_form_render_array));
      }
    }

    return $response;
  }

  /**
   * Handles AJAX request to unblock a user.
   *
   * @param \Drupal\user\UserInterface $user_to_unblock
   * The user to unblock.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * The AJAX response.
   */
  public function ajaxUnblockUser(UserInterface $user_to_unblock, Request $request): AjaxResponse
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
      // --- USE NEW COMMAND ---
      $response->addCommand(new ShowBootstrapToastCommand($message, $this->t('User unblocked')));
    } else {
      $message = $this->t('%username was not blocked!', ['%username' => $user_to_unblock->getAccountName()]);
      // --- USE NEW COMMAND ---
      $response->addCommand(new ShowBootstrapToastCommand($message, $this->t('Not blocked'), 'error'));
    }

    // Replace the link
    $chat_thread_id = $request->query->get('chat_thread_id');
    $options_for_link_render = [];

    if ($chat_thread_id) {
        // Options for chat popover, as per your request
        $options_for_link_render = [
            'wrapper_classes' => ['mt-3', 'mb-n4', 'mx-n4', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
            'link_classes_base' => ['use-ajax', 'match-abuse-link', 'btn', 'd-block', 'w-100', 'rounded-top-0'],
            'link_classes_block_state' => ['btn-danger', 'text-muted'],
            'link_classes_unblock_state' => ['btn-success', 'text-muted'],
            // Default icon will be used unless overridden here
        ];
    } else {
        // Options for profile page dropdown (or other non-chat contexts)
        $options_for_link_render = [
            'wrapper_classes' => [], // No extra margin for dropdown item wrapper.
            'link_classes_base' => ['use-ajax', 'match-abuse-link', 'dropdown-item'],
            'link_classes_block_state' => ['bg-danger', 'mb-n2', 'text-muted', 'rounded-bottom-2'], // Keep text-only danger for dropdown
            'link_classes_unblock_state' => ['bg-success', 'mb-n2', 'text-muted', 'rounded-bottom-2'], // Keep text-only success for dropdown
        ];
    }
    $link_container_render_array = $this->getBlockLinkRenderArray($user_to_unblock, $chat_thread_id, $options_for_link_render);
    $wrapper_selector = '#match-abuse-block-link-wrapper-' . $user_to_unblock->id();
    $response->addCommand(new ReplaceCommand($wrapper_selector, $link_container_render_array));

    // If a chat_thread_id is provided in the request, rebuild and replace the message form.
    if ($chat_thread_id) {
      $thread_storage = $this->entityTypeManager()->getStorage('match_thread');
      /** @var \Drupal\match_chat\Entity\MatchThreadInterface|null $thread_entity */
      $thread_entity = $thread_storage->load($chat_thread_id);
      if ($thread_entity) {
        // MatchMessageForm::class will resolve to the correct form class.
        $message_form_render_array = $this->formBuilder()->getForm(MatchMessageForm::class, $thread_entity);
        $message_form_wrapper_id = '#match-message-form-wrapper-' . $thread_entity->id();
        $response->addCommand(new ReplaceCommand($message_form_wrapper_id, $message_form_render_array));
      }
    }

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
