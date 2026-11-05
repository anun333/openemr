<?php

/**
 * The insurance package's container definitions: one factory per contract it implements, in the format of core's
 * config/ files (Firehed/container), so the file a plugin's getConfigFiles() would name (#12643) is this one.
 * Core requires this package (composer.json) and reads this file the first time it asks for a contract
 * (OpenEMR\Core\RequiredPackages, through OpenEMR\BC\ServiceContainer); nothing in core names these classes.
 * Composer autoloads the package's namespace.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\Modules\Insurance\{
    InsuranceCoverage,
    InsurancePayers,
};
use OpenEMR\Services\Insurance\Contract\{
    Coverage,
    Payer,
};

return [
    Coverage::class => static fn(): Coverage => new InsuranceCoverage(),
    Payer::class => static fn(): Payer => new InsurancePayers(),
];
