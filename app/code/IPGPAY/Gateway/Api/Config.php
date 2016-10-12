<?php
/**
  * @version $Id$
  * @copyright Copyright (c) 2002 - 2016 IPG Holdings Limited (a company incorporated in Cyprus).
  * All rights reserved. Use is strictly subject to licence terms & conditions.
  * This computer software programme is protected by copyright law and international treaties.
  * Unauthorised reproduction, reverse engineering or distribution of the programme, or any part of it, may
  * result in severe civil and criminal penalties and will be prosecuted to the maximum extent permissible at law.
  * For further information, please contact the copyright owner by email copyright@ipgholdings.net
**/
namespace IPGPAY\Gateway\Api;

/**
 * Class Config
 * @package IPGPAY
 */
class Config
{
    /**
     *
     */
    const API_BASE_URL = 'https://my.ipgpay.com';
    /**
     *
     */
    const API_CLIENT_ID = '';
    /**
     *
     */
    const API_KEY = '';
    /**
     *
     */
    const NOTIFY = 1;
    /**
     *
     */
    const TEST_MODE = 0;
    /**
     *
     */
    const DEFAULT_SIGNATURE_LIFETIME = 24;
    /**
     *
     */
    const SIGNATURE_TYPE = 'PSSHA1';
}