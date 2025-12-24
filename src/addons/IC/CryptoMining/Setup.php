<?php

namespace IC\CryptoMining;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;

/**
 * Crypto Mining Simulation - Setup
 * Version: 1.0.1 (Skeleton)
 */
class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;
	
	// Install steps will be added in v1.0.2 (database creation)
	// Upgrade steps will be added as needed
	// Uninstall steps will be added in v1.0.2 (database cleanup)
}
