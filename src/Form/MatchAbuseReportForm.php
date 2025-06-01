<?php

namespace Drupal\match_abuse\Form;

use Drupal\Core\Entity\ContentEntityForm; // <--- MUST BE THIS
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\user\Entity\User;

/**
 * Form controller for Match Abuse Report add forms.
 *
 * @ingroup match_abuse
 */
class MatchAbuseReportForm extends ContentEntityForm
{ // <--- MUST EXTEND THIS

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    /* @var \Drupal\match_abuse\Entity\MatchAbuseReport $entity */
    $entity = $this->entity;

    $user_to_report_id = $this->getRequest()->attributes->get('user_to_report');
    $user_being_reported = NULL;

    if ($user_to_report_id && $user_being_reported = User::load($user_to_report_id)) {
      $entity->set('reported_uid', $user_to_report_id);
      $form_state->set('user_being_reported', $user_being_reported);
    }

    $entity->set('reporter_uid', $this->currentUser()->id());
    $form = parent::buildForm($form, $form_state);

    if ($user_being_reported) {
      $form['#title'] = $this->t('Report Abuse: %username', ['%username' => $user_being_reported->getAccountName()]);
    }

    if (isset($form['reporter_uid'])) {
      $form['reporter_uid']['#access'] = FALSE;
    }
    if (isset($form['reported_uid'])) {
      $form['reported_uid']['#access'] = FALSE;
    }
    if (isset($form['status'])) {
      $form['status']['#access'] = FALSE;
    }
    if (isset($form['user_notes'])) {
      $form['user_notes']['#access'] = FALSE;
    }
    if (isset($form['admin_notes'])) {
      $form['admin_notes']['#access'] = FALSE;
    }

    if ($user_being_reported) {
      $form['reported_user_display'] = [
        '#type' => 'item',
        '#title' => $this->t('User being reported'),
        '#markup' => $user_being_reported->toLink()->toString(),
        '#weight' => -10,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state)
  {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);
    $user_being_reported = $entity->get('reported_uid')->entity;

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Your abuse report against %username has been submitted.', [
          '%username' => $user_being_reported->toLink()->toString(),
        ]));

        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'match_abuse';
        $key = 'abuse_report_notification';
        $to = \Drupal::config('system.site')->get('mail');
        $params['report'] = $entity;
        $langcode = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
        $send = TRUE;

        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
        if ($result['result'] !== true) {
          $this->messenger()->addError(t('There was a problem sending the abuse report notification email.'));
        }
        break;

      default:
        $this->messenger()->addStatus($this->t('The abuse report %label has been saved.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirectUrl($user_being_reported->toUrl());
  }
}
