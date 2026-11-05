<?php

/**
 * The insurance module is required: core loads it itself, in every entry point, the first time it asks the
 * coverage or payer contract (OpenEMR\Core\RequiredModules reads config/services.php). This file exists only so
 * that enabling the module in the Module Manager, which lists every folder here, loads nothing twice and logs no
 * missing bootstrap. Enabling or disabling it there changes nothing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

return;
