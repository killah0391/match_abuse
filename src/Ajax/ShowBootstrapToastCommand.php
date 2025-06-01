<?php

namespace Drupal\match_abuse\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * An AJAX command for showing a Bootstrap Toast message.
 *
 * @ingroup ajax
 */
class ShowBootstrapToastCommand implements CommandInterface
{

  /**
   * The message to be shown in the toast body.
   *
   * @var string
   */
  protected $message;

  /**
   * The title for the toast header.
   *
   * @var string
   */
  protected $title;

  /**
   * The type of message (e.g., 'status', 'warning', 'error').
   * Used for styling, though basic toast is shown here.
   *
   * @var string
   */
  protected $type;

  /**
   * Constructs a ShowBootstrapToastCommand object.
   *
   * @param string $message
   * The message to display.
   * @param string $title
   * (Optional) The title for the toast. Defaults to 'Notification'.
   * @param string $type
   * (Optional) The type of message. Defaults to 'status'.
   */
  public function __construct($message, $title = 'Notification', $type = 'status')
  {
    $this->message = $message;
    $this->title = $title;
    $this->type = $type;
  }

  /**
   * {@inheritdoc}
   */
  public function render()
  {
    return [
      'command' => 'showBootstrapToast',
      'message' => $this->message,
      'title' => $this->title,
      'type' => $this->type,
    ];
  }
}
