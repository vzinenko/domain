<?php

namespace Drupal\domain\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Drupal\domain\DomainLoader;
use Drupal\domain\DomainNegotiator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides domain-based access control.
 *
 * @ViewsAccess(
 *   id = "domain",
 *   title = @Translation("Domain"),
 *   help = @Translation("Access will be granted when accessed from an allowed domain.")
 * )
 */
class Domain extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * Domain storage.
   *
   * @var \Drupal\domain\DomainLoader
   */
  protected $domainLoader;

  /**
   * Domain negotiation.
   *
   * @var \Drupal\domain\DomainNegotiator
   */
  protected $domainNegotiator;

  /**
   * Constructs a Role object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param DomainLoader $domain_loader
   *   The domain storage loader.
   * @param DomainNegotiator $domain_negotiator
   *   The domain negotiator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DomainLoader $domain_loader, DomainNegotiator $domain_negotiator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->domainLoader = $domain_loader;
    $this->domainNegotiator = $domain_negotiator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('domain.loader'),
      $container->get('domain.negotiator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $id = $this->domainNegotiator->getActiveId();
    $options = array_filter($this->options['domain']);
    return isset($options[$id]);
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    if ($this->options['domain']) {
      $route->setRequirement('_domain', (string) implode('+', $this->options['domain']));
    }
  }

  public function summaryTitle() {
    $count = count($this->options['domain']);
    if ($count < 1) {
      return $this->t('No domain(s) selected');
    }
    elseif ($count > 1) {
      return $this->t('Multiple domains');
    }
    else {
      $domains = $this->domainLoader->loadOptionsList();
      $domain = reset($this->options['domain']);
      return $domains[$domain];
    }
  }


  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['domain'] = array('default' => array());

    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['domain'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Domain'),
      '#default_value' => $this->options['domain'],
      '#options' => $this->domainLoader->loadOptionsList(),
      '#description' => $this->t('Only the checked domain(s) will be able to access this display.'),
    );
  }

  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $domain = $form_state->getValue(array('access_options', 'domain'));
    $domain = array_filter($domain);

    if (!$domain) {
      $form_state->setError($form['domain'], $this->t('You must select at least one domain if type is "by domain"'));
    }

    $form_state->setValue(array('access_options', 'domain'), $domain);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    foreach (array_keys($this->options['domain']) as $id) {
      if ($domain = $this->domainLoader->load($id)) {
        $dependencies[$domain->getConfigDependencyKey()][] = $domain->getConfigDependencyName();
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url.site'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
