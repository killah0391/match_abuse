<?php

namespace Drupal\match_abuse;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Match Abuse Report entities.
 */
class MatchAbuseReportListBuilder extends EntityListBuilder
{

  /**
   * {@inheritdoc}
   */
  public function buildHeader()
  {
    $header['id'] = $this->t('Report ID');
    $header['reason'] = $this->t('Reason');
    $header['reporter'] = $this->t('Reporter');
    $header['reported'] = $this->t('Reported');
    $header['status'] = $this->t('Status');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity)
  {
    /** @var \Drupal\match_abuse\Entity\MatchAbuseReport $entity */
    $row['id'] = $entity->id();
    $row['reason'] = $entity->toLink($entity->get('reason')->value);
    $reporter = $entity->get('reporter_uid')->entity;
    $row['reporter'] = $reporter ? $reporter->toLink() : $this->t('N/A');
    $reported = $entity->get('reported_uid')->entity;
    $row['reported'] = $reported ? $reported->toLink() : $this->t('N/A');
    $row['status'] = $entity->get('status')->value;
    $row['created'] = \Drupal::service('date.formatter')->format($entity->get('created')->value, 'short');
    return $row + parent::buildRow($entity);
  }
}
