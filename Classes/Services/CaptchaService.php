<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Sebastian Fischer <typo3@evoweb.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
namespace Evoweb\Recaptcha\Services;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class RecaptchaService
 */
class CaptchaService
{

    /**
     * @var array
     */
    protected $configuration = [];

    /**
     * @var ContentObjectRenderer
     */
    protected $contentObject;

    /**
     * @return self
     * @throws \Exception
     */
    public function __construct()
    {
        $configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['recaptcha']);

        if (!is_array($configuration)) {
            $configuration = [];
        }

        if (
            isset($GLOBALS['TSFE'])
            && $GLOBALS['TSFE'] instanceof TypoScriptFrontendController
            && isset($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_recaptcha.'])
            && is_array($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_recaptcha.'])
        ) {
            ArrayUtility::mergeRecursiveWithOverrule(
                $configuration,
                $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_recaptcha.']
            );
        }

        if (!is_array($configuration) || empty($configuration)) {
            throw new \Exception('Please configure plugin.tx_recaptcha. before rendering the recaptcha', 1417680291);
        }

        $this->configuration = $configuration;
        $this->contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
    }

    /**
     * Build reCAPTCHA Frontend HTML-Code
     *
     * @return string reCAPTCHA HTML-Code
     */
    public function getReCaptcha()
    {
        return $this->contentObject->stdWrap($this->configuration['public_key'], $this->configuration['public_key.']);
    }

    /**
     * Validate reCAPTCHA challenge/response
     *
     * @return array Array with verified- (boolean) and error-code (string)
     */
    public function validateReCaptcha()
    {
        $request = [
            'secret' => $this->configuration['private_key'],
            'response' => trim(GeneralUtility::_GP('g-recaptcha-response')),
            'remoteip' => GeneralUtility::getIndpEnv('REMOTE_ADDR'),
        ];

        $result = ['verified' => false, 'error' => ''];
        if (empty($request['response'])) {
            $result['error'] = 'Recaptcha response missing';
        } else {
            $response = $this->queryVerificationServer($request);
            if (!$response) {
                $result['error'] = 'Verification server did not responde';
            }

            if ($response['success']) {
                $result['verified'] = true;
            } else {
                $result['error'] = $response['error-codes'];
            }
        }

        return $result;
    }

    /**
     * Query reCAPTCHA server for captcha-verification
     *
     * @param array $data
     * @return array Array with verified- (boolean) and error-code (string)
     */
    protected function queryVerificationServer($data)
    {
        $verifyServerInfo = @parse_url($this->configuration['verify_server']);

        if (empty($verifyServerInfo)) {
            return [false, 'recaptcha-not-reachable'];
        }

        $request = GeneralUtility::implodeArrayForUrl('', $data);
        $response = GeneralUtility::getUrl($this->configuration['verify_server'] . '?' . $request);

        return json_decode($response, true);
    }

}
