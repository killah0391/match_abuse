<?php

namespace Drupal\match_abuse\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Render\Markup;

/**
 * Form controller for user replies on Match Abuse Reports.
 */
class MatchAbuseReportUserReplyForm extends ContentEntityForm
{

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    /** @var \Drupal\match_abuse\Entity\MatchAbuseReport $entity */
    $entity = $this->entity;

    // Check access again within the form (belt and suspenders)
    if (
      $entity->get('reporter_uid')->target_id != $this->currentUser()->id() ||
      $entity->get('status')->value != 'waiting_user'
    ) {
      $this->messenger()->addError(t("You do not have permission to reply to this report at this time."));
      return []; // Return empty form or redirect
    }

    $form['#title'] = $this->t('Reply to Report #%id: %reason', [
      '%id' => $entity->id(),
      '%reason' => $entity->label(),
    ]);

    // Display existing notes (read-only).
    $form['conversation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Conversation History'),
      '#weight' => -20,
    ];

    // $admin_notes_html = '<h4>' . $this->t('Admin Notes') . '</h4>';
    // $admin_notes = $entity->get('admin_notes')->getValue();
    // if (empty($admin_notes)) {
    //   $admin_notes_html .= '<p><i>' . $this->t('No admin notes yet.') . '</i></p>';
    // } else {
    //   $admin_notes_html .= '<ul>';
    //   foreach ($admin_notes as $note) {
    //     $admin_notes_html .= '<li>' . nl2br(htmlspecialchars($note['value'])) . '</li>';
    //   }
    //   $admin_notes_html .= '</ul>';
    // }
    // $form['conversation']['admin_notes_display'] = ['#markup' => Markup::create($admin_notes_html)];

    $user_notes_html = '<h4>' . $this->t('Your Notes') . '</h4>';
    $user_notes = $entity->get('user_notes')->getValue();
    if (empty($user_notes)) {
      $user_notes_html .= '<p><i>' . $this->t('You have not added any notes yet.') . '</i></p>';
    } else {
      $user_notes_html .= '<ul>';
      foreach ($user_notes as $note) {
        $user_notes_html .= '<li>' . nl2br(htmlspecialchars($note['value'])) . '</li>';
      }
      $user_notes_html .= '</ul>';
    }
    $form['conversation']['user_notes_display'] = ['#markup' => Markup::create($user_notes_html)];


    // Add the new note field. We need a *new* field, not the entity's one,
    // because we only want to *add*, not edit existing ones.
    $form['new_user_note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Add Your Reply'),
      '#required' => TRUE,
      '#weight' => -10,
    ];

    // Build the rest (will likely be hidden or removed).
    $form = parent::buildForm($form, $form_state);

    // Hide all original entity fields except actions.
    foreach ($form as $key => $value) {
      if (!in_array($key, ['new_user_note', 'conversation', 'actions', '#'])) {
        // Check if key starts with '#' before hiding
        if ($key[0] !== '#') {
          $form[$key]['#access'] = FALSE;
        }
      }
    }
    // Ensure actions are visible.
    $form['actions']['#access'] = TRUE;
    $form['actions']['submit']['#value'] = $this->t('Submit Reply');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state)
  {
    /** @var \Drupal\match_abuse\Entity\MatchAbuseReport $entity */
    $entity = $this->entity;
    $new_note = $form_state->getValue('new_user_note');

    // Add the new note to the user_notes field.
    $entity->get('user_notes')->appendItem(['value' => $new_note]);

    // Change status to 'user_replied'.
    $entity->set('status', 'user_replied');

    // Save the entity.
    $entity->save();

    $this->messenger()->addStatus($this->t('Your reply has been added.'));
    $form_state->setRedirect('entity.user.canonical', ['user' => $this->currentUser()->id()]);
  }
}
