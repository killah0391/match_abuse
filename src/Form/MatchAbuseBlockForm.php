<?php

namespace Drupal\match_abuse\Form;

use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

class MatchAbuseBlockForm extends FormBase {

  public function getFormId() {
    return 'match_abuse_block_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $user_to_block = NULL) {
    $form['user_to_block'] = [
      '#type' => 'hidden',
      '#value' => $user_to_block,
    ];

    $user = User::load($user_to_block);

    $form['message'] = [
      '#markup' => $this->t('Are you sure you want to block %username?', ['%username' => $user->getAccountName()]),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Block User'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_to_block = $form_state->getValue('user_to_block');
    $current_user = $this->currentUser();

    $block = \Drupal::entityTypeManager()->getStorage('match_abuse_block')->create([
      'blocker_uid' => $current_user->id(),
      'blocked_uid' => $user_to_block,
    ]);
    $block->save();

    $user = User::load($user_to_block);
    $this->messenger()->addStatus($this->t('You have blocked %username.', ['%username' => $user->getAccountName()]));
    $form_state->setRedirectUrl(Url::fromRoute('entity.user.canonical', ['user' => $user_to_block]));
  }

}
