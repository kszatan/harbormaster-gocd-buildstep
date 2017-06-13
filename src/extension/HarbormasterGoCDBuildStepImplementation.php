<?php
/**
* MIT License
*
* Copyright (c) 2017 Krzysztof Szatan
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
*/

final class HarbormasterGoCDBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Build with GoCD');
  }

  public function getGenericDescription() {
    return pht('Trigger a pipeline in GoCD.');
  }

  public function getBuildStepGroupKey() {
    return HarbormasterExternalBuildStepGroup::GROUPKEY;
  }

  public function getDescription() {
    return pht('Run a build in GoCD.');
  }

  public function getEditInstructions() {
    return 'GoCD buildstep';
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    $url = $this->getRequestUrl();
    $query = $this->getQueryString($build_target->getVariables());
    $future = $this->prepareHttpsFuture($url, $query);

    $this->resolveFutures(
      $build,
      $build_target,
      array($future));

    $this->logHTTPResponse($build, $build_target, $future, $url);

    list($status, $response) = $future->resolve();
    if ($status->isError()) {
      throw new HarbormasterBuildFailureException();
    }
  }

  public function getFieldSpecifications() {
    return array(
      'server.url' => array(
        'name' => pht('GoCD base URL'),
        'type' => 'text',
        'required' => true,
      ),
      'credential' => array(
        'name' => pht('Credentials'),
        'type' => 'credential',
        'credential.type'
          => PassphrasePasswordCredentialType::CREDENTIAL_TYPE,
        'credential.provides'
          => PassphrasePasswordCredentialType::PROVIDES_TYPE,
        'required' => true,
      ),
      'pipeline' => array(
        'name' => pht('Pipeline Name'),
        'type' => 'text',
        'required' => true,
      ),
      'query.string' => array(
        'name' => pht('POST Query String'),
        'type' => 'text',
        'required' => false,
      ),
    );
  }

  public function supportsWaitForMessage() {
    return true;
  }

  public function shouldWaitForMessage(HarbormasterBuildTarget $target) {
    return true;
  }

  protected function getRequestUrl() {
    $settings = $this->getSettings();
    $server_url = rtrim($settings['server.url'], '/');
    $url = sprintf(
      '%s/go/api/pipelines/%s/schedule',
      $server_url,
      $settings['pipeline']);
    return $url;
  }

  protected function getQueryString(array $variables) {
    $settings = $this->getSettings();
    $query = $this->mergeVariables(
      'vurisprintf',
      $settings['query.string'],
      $variables);
    return $query;
  }

  protected function prepareHttpsFuture($url, $query) {
    $future = id(new HTTPSFuture($url, $query))
      ->setMethod('POST')
      ->addHeader('Confirm', 'true')
      ->setTimeout(60);
      
    $viewer = PhabricatorUser::getOmnipotentUser();
    $credential_phid = $this->getSetting('credential');
    if ($credential_phid) {
      $key = PassphrasePasswordKey::loadFromPHID(
        $credential_phid,
        $viewer);
      $future->setHTTPBasicAuthCredentials(
        $key->getUsernameEnvelope()->openEnvelope(),
        $key->getPasswordEnvelope());
    }

    return $future;
  }

}
