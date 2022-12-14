<?php
error_reporting(E_ALL & ~E_NOTICE);
include('EngineConfigs.cfg.php');
define('DS', DIRECTORY_SEPARATOR);
define('DIR', dirname(__FILE__));

$configsDir = dirname(DIR) . DS . 'EngineConfigs' . DS;
$outputDir = dirname(DIR) . DS . 'GameData' . DS . 'RealismSimplified' . DS . 'EngineConfigs' . DS;
$locEnFile = dirname(DIR) . DS . 'GameData' . DS . 'RealismSimplified' . DS . 'Localization' . DS . 'en-us.cfg';
$locFile = file_get_contents($locEnFile);
$locAddLines = '';

$mask = $configsDir . '*.txt';
if ($files = glob($mask)) {
	$configs = array();
	foreach ($files as $filename) {
		if (is_file($filename)) {
			$configs[] = array(basename($filename), basename($filename, '.txt'));
		}
	}
}

foreach ($configs as $fileNames)
{
	$lines = file($configsDir . $fileNames[0]);
	$config = array();
	$siblings = array();
	$thrustCurve = array();
	$upgradeIcons = array();
	$configName = '';
	$mode = null;
	$nextMode = null;
	foreach ($lines as $line) {
		$line = trim($line);
		list($data, $comment) = explode('//', $line, 2);
		$data = trim($data);
		if ($data && !$configName)
		{
			$configName = $data;
			continue;
		}
		if (strtolower($data) == 'thrustcurve') {
			$mode = 'thrustCurve';
			continue;
		}
		else if (strtolower($data) == 'siblings') {
			$mode = 'siblings';
			continue;
		}
		else if (strtolower($data) == 'upgradeicons') {
			$mode = 'upgradeIcons';
			continue;
		}
		if ($data ==  '{') {
			continue;
		}
		if (!is_null($mode) && ($data == '}')) {
			$mode = null;
			continue;
		}
		list($key, $value) = explode('=', $data, 2);
		$key = strtolower(trim($key));
		$value = trim($value);
		if (is_null($mode)) {
			switch ($key)
			{
				case 'propellants':
					$config['propellants'] = $value;
					break;
				case 'type':
					$config['type'] = $value;
					break;
				case 'pressurefed':
					$config['pressureFed'] = $value;
					break;
				case 'minthrust':
					$config['minThrust'] = $value;
					break;
				case 'maxthrust':
					$config['maxThrust'] = $value;
					break;
				case 'ispsl':
					$config['ispSL'] = $value;
					break;
				case 'ispvac':
					$config['ispVac'] = $value;
					break;
				case 'ignitions':
					$config['ignitions'] = $value;
					break;
				case 'ullage':
					$config['ullage'] = $value;
					break;
				case 'ratedburntime':
					$config['ratedBurnTime'] = $value;
					break;
				case 'gimbalrange':
					$config['gimbalRange'] = $value;
					break;
				case 'mass':
					$config['mass'] = $value;
					break;
				case 'drymass':
					$config['dryMass'] = $value;
					break;
				case 'solidfuelmass':
					$config['solidFuelMass'] = $value;
					break;
				case 'cost':
					$config['cost'] = $value;
					break;
				case 'drycost':
					$config['dryCost'] = $value;
					break;
				case 'entrycost':
					$config['entryCost'] = $value;
					break;
				case 'upgradecost':
					$config['upgradeCost'] = $value;
					break;
				case 'isupgrade':
					$config['isUpgrade'] = $value;
					break;
			}
		}
		else if ($mode == 'thrustCurve') {
			list($data1, $comment) = explode('//', $line, 2);
			list($key, $value) = explode('=', $data1, 2);
			$thrustCurve[] = str_replace("\t", ' ', trim($value));
		}
		else if ($mode == 'siblings') {
			list($data1, $comment) = explode('//', $line, 2);
			list($key, $value) = explode('=', $data1, 2);
			$key = trim($key);
			$value = trim($value);
			$siblings[$key] = $value;
		}
		else if ($mode == 'upgradeIcons') {
			list($data1, $comment) = explode('//', $line, 2);
			list($needs, $partName) = explode(',', $data1, 2);
			$needs = trim($needs);
			$partName = trim($partName);
			if (!$partName) {
				$partName = $needs;
				$needs = null;
			}
			$upgradeIcons[] = array('needs' => $needs, 'partName' => $partName);
		}
	}
	if (!isset($config['dryMass']) && isset($config['mass'])) {
		$config['dryMass'] = $config['mass'];
	}
	if (!isset($config['mass']) && isset($config['dryMass'])) {
		$config['mass'] = $config['dryMass'];
	}
	if (!isset($config['dryCost']) && isset($config['cost'])) {
		$config['dryCost'] = $config['cost'];
	}
	if (!isset($config['costsAutoGenerated'])) {
		$config['costsAutoGenerated'] = 'false';
	}

	// Auto-generate prices
//	$cfg .= str_replace('%CONFIG%', $configName, $autoGenerateEnginesPricesTemplateHeader);
	switch ($config['type']) {
		case 'lower_stage':
			// Lower stage engine prices are generated as follows:
			// cost = ISPCoeff * (((ISP(vacuum, s) + ISP(sea level, s)) / (2 * ISPCoeff)) ^ ISPPower)  * (2 * MaxThrust(vacuum, KN) - MinThrust(vacuum, KN)) * CostCoeff / 10 + BaseCost
			// upgradeCost = cost * UpgradeCostCoeff
			// entryCost = cost * EntryCostCoeff
			$settings = $autoGeneratedPricesSettings['lower_stage'];
			$cost = $settings['ISP_coeff'] * pow(($config['ispVac'] + $config['ispSL']) / (2 * $settings['ISP_coeff']), $settings['ISP_power']);
			$cost *= (2 * $config['maxThrust'] - $config['minThrust']) * $settings['cost_coeff'] / 10;
			$cost += $settings['base_cost'];
			$cost = floor($cost);
			$config['cost'] = $cost;
			$config['dryCost'] = $cost;
			$config['entryCost'] = $cost * $settings['entry_cost_coeff'];
			$config['upgradeCost'] = $cost * $settings['upgrade_cost_coeff'];
			$config['costsAutoGenerated'] = true;
			break;
		case 'sustainer':
			// Sustainer engine prices are generated as follows:
			// cost = ISPCoeff * (((ISP(vacuum, s) + ISP(sea level, s)) / (2 * ISPCoeff)) ^ ISPPower)  * (2 * MaxThrust(vacuum, KN) - MinThrust(vacuum, KN)) * CostCoeff / 10 + BaseCost
			// upgradeCost = cost * UpgradeCostCoeff
			// entryCost = cost * EntryCostCoeff
			$settings = $autoGeneratedPricesSettings['sustainer'];
			$cost = $settings['ISP_coeff'] * pow(($config['ispVac'] + $config['ispSL']) / (2 * $settings['ISP_coeff']), $settings['ISP_power']);
			$cost *= (2 * $config['maxThrust'] - $config['minThrust']) * $settings['cost_coeff'] / 10;
			$cost += $settings['base_cost'];
			$cost = floor($cost);
			$config['cost'] = $cost;
			$config['dryCost'] = $cost;
			$config['entryCost'] = $cost * $settings['entry_cost_coeff'];
			$config['upgradeCost'] = $cost * $settings['upgrade_cost_coeff'];
			$config['costsAutoGenerated'] = true;
			break;
		case 'upper_stage':
			// Upper stage engine prices are generated as follows:
			// cost = ISPCoeff * ((ISP(vacuum, s) / ISPCoeff) ^ ISPPower)  * (2 * MaxThrust(vacuum, KN) - MinThrust(vacuum, KN)) * CostCoeff / 10 + BaseCost
			// upgradeCost = cost * UpgradeCostCoeff
			// entryCost = cost * EntryCostCoeff
			$settings = $autoGeneratedPricesSettings['upper_stage'];
			$cost = $settings['ISP_coeff'] * pow($config['ispVac'] / $settings['ISP_coeff'], $settings['ISP_power']);
			$cost *= (2 * $config['maxThrust'] - $config['minThrust']) * $settings['cost_coeff'] / 10;
			$cost += $settings['base_cost'];
			$cost = floor($cost);
			$config['cost'] = $cost;
			$config['dryCost'] = $cost;
			$config['entryCost'] = $cost * $settings['entry_cost_coeff'];
			$config['upgradeCost'] = $cost * $settings['upgrade_cost_coeff'];
			$config['costsAutoGenerated'] = true;
			break;
		case 'OMS':
			// OMS engines prices are generated as follows:
			// cost = ISPCoeff * ((ISP(vacuum, s) / ISPCoeff) ^ ISPPower) * MaxThrust(vacuum, KN) * PressureFedCostCoeff * (IgnitionsCount ^ IgnitionsPower) * CostCoeff + BaseCost
			// upgradeCost = cost * UpgradeCostCoeff
			// entryCost = cost * EntryCostCoeff
			$settings = $autoGeneratedPricesSettings['OMS'];
			$cost = $settings['ISP_coeff'] * pow($config['ispVac'] / $settings['ISP_coeff'], $settings['ISP_power']);
			$cost *= $config['maxThrust'];
			if ($config['pressureFed']) {
				$cost *= $settings['pressureFed_coeff'];
			}
			if ($config['ignitions'] == -1) {
				$cost *= pow($settings['ignitions_infinite_eq'], $settings['ignitions_power']);
			}
			else {
				$cost *= pow($config['ignitions'], $settings['ignitions_power']);
			}
			$cost *= $settings['cost_coeff'];
			$cost += $settings['base_cost'];
			$cost = floor($cost);
			$config['cost'] = $cost;
			$config['dryCost'] = $cost;
			$config['entryCost'] = $cost * $settings['entry_cost_coeff'];
			$config['upgradeCost'] = $cost * $settings['upgrade_cost_coeff'];
			$config['costsAutoGenerated'] = true;
			break;
		case 'nuclear':
			// Nuclear engines prices are generated as follows:
			// cost = ISPCoeff * ((ISP(vacuum, s) / ISPCoeff) ^ ISPPower) * (MaxThrust(vacuum, KN) ^ ThrustPower) * ((MaxThrust(vacuum, KN) / (EngineMass(t) * 9.81)) ^ TWRPower) * (IgnitionsCount ^ IgnitionsPower) * CostCoeff + BaseCost
			// upgradeCost = cost * UpgradeCostCoeff
			// entryCost = cost * EntryCostCoeff
			$settings = $autoGeneratedPricesSettings['nuclear'];
			$cost = $settings['ISP_coeff'] * pow($config['ispVac'] / $settings['ISP_coeff'], $settings['ISP_power']);
			$cost *= pow($config['maxThrust'], $settings['thrust_power']);
			$cost *= pow($config['maxThrust'] / ($config['mass'] * 9.81), $settings['TWR_power']);
			if ($config['ignitions'] == -1) {
				$cost *= pow($settings['ignitions_infinite_eq'], $settings['ignitions_power']);
			}
			else {
				$cost *= pow($config['ignitions'], $settings['ignitions_power']);
			}
			$cost *= $settings['cost_coeff'];
			$cost += $settings['base_cost'];
			$cost = floor($cost);
			$config['cost'] = $cost;
			$config['dryCost'] = $cost;
			$config['entryCost'] = $cost * $settings['entry_cost_coeff'];
			$config['upgradeCost'] = $cost * $settings['upgrade_cost_coeff'];
			$config['costsAutoGenerated'] = true;
			break;
		case 'SRB':
			// SRBs prices are generated as follows:
			// cost = ((((ISP(vacuum, s) / ISPCoeff) ^ ISPPower) * MaxThrust(vacuum, KN) * ((Full SRB mass / Dry SRB mass) ^ MassRatioPower) * CostCoeff + Solid fuel cost + BaseCost
			// upgradeCost = cost * UpgradeCostCoeff
			// entryCost = cost * EntryCostCoeff
			$settings = $autoGeneratedPricesSettings['SRB'];
			$cost = pow($config['ispVac'] / $settings['ISP_coeff'], $settings['ISP_power']);
			$cost *= $config['maxThrust'];
			$cost *= pow(($config['dryMass'] + $config['solidFuelMass']) / $config['dryMass'], $settings['massRatio_power']);
			$cost *= $settings['cost_coeff'];
			$cost += ($settings['solidFuelMass'] * 19.04);
			$cost += $settings['base_cost'];
			$cost = floor($cost);
			$config['cost'] = $cost;
			$config['dryCost'] = $cost;
			$config['entryCost'] = $cost * $settings['entry_cost_coeff'];
			$config['upgradeCost'] = $cost * $settings['upgrade_cost_coeff'];
			$config['costsAutoGenerated'] = true;
			break;
	}
	if (!isset($config['costsAutoGenerated'])) {
		$config['costsAutoGenerated'] = false;
	}

//	$cfg .= str_replace('%CONFIG%', $configName, $autoGenerateEnginesPricesTemplateFooter);

	$locName = preg_replace('/(\D)-/', '${1}', $configName, 1);
	$locName = str_replace('-', '_', $locName);
	$config['title'] = '#LOC_RS_Engine_' . $locName . '_title';
	$config['description'] = '#LOC_RS_Engine_' . $locName . '_desc';
	if (mb_strpos($locFile, $config['title']) === false) {
		$locAddLines .= "\t\t" . $config['title'] . " = \r\n";
		$locAddLines .= "\t\t" . $config['description'] . " = \r\n";
	}
	$cfg = "@RS_ENGINE_CONFIGS\r\n{\r\n\t" .  $configName . "\r\n\t{\r\n";
	if (isset($config['title'])) {
		$cfg .= "\t\ttitle = " . $config['title'] . "\r\n";
	}
	if (isset($config['description'])) {
		$cfg .= "\t\tdescription = " . $config['description'] . "\r\n";
	}
	if (isset($config['propellants'])) {
		$cfg .= "\t\tpropellants = " . $config['propellants'] . "\r\n";
	}
	if (isset($config['type'])) {
		$cfg .= "\t\ttype = " . $config['type'] . "\r\n";
	}
	if (isset($config['pressureFed'])) {
		$cfg .= "\t\tpressureFed = " . $config['pressureFed'] . "\r\n";
	}
	if (isset($config['minThrust'])) {
		$cfg .= "\t\tminThrust = " . $config['minThrust'] . "\r\n";
	}
	if (isset($config['maxThrust'])) {
		$cfg .= "\t\tmaxThrust = " . $config['maxThrust'] . "\r\n";
	}
	if (isset($config['minThrustPercent'])) {
		$cfg .= "\t\tminThrustPercent = " . $config['minThrustPercent'] . "\r\n";
	}
	if (isset($config['ispSL'])) {
		$cfg .= "\t\tispSL = " . $config['ispSL'] . "\r\n";
	}
	if (isset($config['ispVac'])) {
		$cfg .= "\t\tispVac = " . $config['ispVac'] . "\r\n";
	}
	if (isset($config['ignitions'])) {
		$cfg .= "\t\tignitions = " . $config['ignitions'] . "\r\n";
	}
	if (isset($config['ullage'])) {
		$cfg .= "\t\tullage = " . $config['ullage'] . "\r\n";
	}
	if (isset($config['ratedBurnTime'])) {
		$cfg .= "\t\tratedBurnTime = " . $config['ratedBurnTime'] . "\r\n";
	}
	if (isset($config['gimbalRange'])) {
		$cfg .= "\t\tgimbalRange = " . $config['gimbalRange'] . "\r\n";
	}
	if (isset($config['mass'])) {
		$cfg .= "\t\tmass = " . $config['mass'] . "\r\n";
	}
	if (isset($config['dryMass'])) {
		$cfg .= "\t\tdryMass = " . $config['dryMass'] . "\r\n";
	}
	if (isset($config['solidFuelMass'])) {
		$cfg .= "\t\tsolidFuelMass = " . $config['solidFuelMass'] . "\r\n";
	}
	if (isset($config['cost'])) {
		$cfg .= "\t\tcost = " . $config['cost'] . "\r\n";
	}
	if (isset($config['dryCost'])) {
		$cfg .= "\t\tdryCost = " . $config['dryCost'] . "\r\n";
	}
	if (isset($config['entryCost'])) {
		$cfg .= "\t\tentryCost = " . $config['entryCost'] . "\r\n";
	}
	if (isset($config['upgradeCost'])) {
		$cfg .= "\t\tupgradeCost = " . $config['upgradeCost'] . "\r\n";
	}
	if (isset($config['costsAutoGenerated'])) {
		$cfg .= "\t\tcostsAutoGenerated = " . $config['costsAutoGenerated'] . "\r\n";
	}
	$cfg .= "\t}\r\n}\r\n\r\n";

	if ($config) {
		$cfg .= '@PART[*]:HAS[~RS_IgnorePart[],~RS_IgnoreEngine[],@MODULE[ModuleEngines*]:HAS[~RS_IgnoreEngine[],#RS_EngineConfig[' . $configName . ']]]:AFTER[zzzRealismSimplified]
{
';
		$cfg .= config_param('title', 'RS_EngineTitle');
		$cfg .= config_param('description', 'RS_EngineDescription');
		$cfg .= config_param('propellants', 'RS_EnginePropellants');
		$cfg .= config_param('type', 'RS_EngineType');
		$cfg .= config_param('pressureFed', 'RS_EnginePressureFed');
		$cfg .= config_param('minThrust', 'RS_MinThrust');
		$cfg .= config_param('maxThrust', 'RS_MaxThrust');
		$cfg .= config_param('minThrustPercent', 'RS_MinThrustPercent');
		$cfg .= config_param('ispSL', 'RS_ISPSL');
		$cfg .= config_param('ispVac', 'RS_ISPVac');
		$cfg .= config_param('ignitions', 'RS_EngineIgnitions');
		$cfg .= config_param('ullage', 'RS_EngineUllage');
		$cfg .= config_param('ratedBurnTime', 'RS_EngineRatedBurnTime');
		$cfg .= config_param('gimbalRange', 'RS_EngineGimbalRange');
		$cfg .= config_param('mass', 'RS_EngineMass');
		$cfg .= config_param('dryMass', 'RS_EngineDryMass');
		$cfg .= config_param('solidFuelMass', 'RS_SolidFuelMass');
		$cfg .= config_param('cost', 'RS_EngineCost');
		$cfg .= config_param('dryCost', 'RS_EngineDryCost');
		$cfg .= config_param('entryCost', 'RS_EngineEntryCost');
		$cfg .= config_param('upgradeCost', 'RS_EngineUpgradeCost');
		$cfg .= config_param('costsAutoGenerated', 'RS_EnginePriceAutoGenerated');

		if ($thrustCurve) {
			$curve = '';
			foreach ($thrustCurve as $value) {
				$curve .= "\t\t\tkey = " . $value . "\r\n";
			}
			$data = str_replace('%CONFIG%', $configName, $engineThrustCurveTemplate);
			$cfg .= str_replace('%CURVE%', $curve, $data);
		}
		$cfg .= '}
';
	}

	$data = str_replace('%CONFIG%', $configName, $engineB9PSConfigTemplate);
	$data = str_replace('%TITLE%', $config['title'], $data);
	$data = str_replace('%DESC%', $config['description'], $data);
	$cfg .= $data;

	$cfg .= b9ps_config_param('title', 'title');
	$cfg .= b9ps_config_param('description', 'descriptionDetail');
	$cfg .= b9ps_config_param('propellants', 'RS_EnginePropellants');
	$cfg .= b9ps_config_param('type', 'RS_EngineType');
	$cfg .= b9ps_config_param('pressureFed', 'RS_EnginePressureFed');
	$cfg .= b9ps_config_param('minThrust', 'RS_MinThrust');
	$cfg .= b9ps_config_param('maxThrust', 'RS_MaxThrust');
	$cfg .= b9ps_config_param('minThrustPercent', 'RS_MinThrustPercent');
	$cfg .= b9ps_config_param('ispSL', 'RS_ISPSL');
	$cfg .= b9ps_config_param('ispVac', 'RS_ISPVac');
	$cfg .= b9ps_config_param('ignitions', 'RS_EngineIgnitions');
	$cfg .= b9ps_config_param('ullage', 'RS_EngineUllage');
	$cfg .= b9ps_config_param('ratedBurnTime', 'RS_EngineRatedBurnTime');
	$cfg .= b9ps_config_param('gimbalRange', 'RS_EngineGimbalRange');
	$cfg .= b9ps_config_param('mass', 'RS_EngineMass');
	$cfg .= b9ps_config_param('dryMass', 'RS_EngineDryMass');
	$cfg .= b9ps_config_param('solidFuelMass', 'RS_SolidFuelMass');
	$cfg .= b9ps_config_param('cost', 'RS_EngineCost');
	$cfg .= b9ps_config_param('dryCost', 'RS_EngineDryCost');

	if ($thrustCurve) {
		$curve = '';
		foreach ($thrustCurve as $value) {
			$curve .= "\t\t\t\tkey = " . $value . "\r\n";
		}
		$data = str_replace('%CONFIG%', $configName, $engineB9PSThrustCurveTemplate);
		$cfg .= str_replace('%CURVE%', $curve, $data);
	}

	if ($siblings) {
		$siblingsCfg = '';
		foreach ($siblings as $key => $value) {
			$siblingsCfg .= "\t\t" . $key . " = " . $value . "\r\n";
		}
		$data = str_replace('%CONFIG%', $configName, $payToPlaySiblingsTemplate);
		$data = str_replace('%SIBLINGS%', $siblingsCfg, $data);
		$cfg .= $data;
	}
	if ((strtolower($config['isUpgrade']) == 'true') && isset($config['upgradeCost'])) {
		$data = str_replace('%CONFIG%',$configName, $partUpgradeTemplate);
		$data = str_replace('%TITLE%', $config['title'], $data);
		$data = str_replace('%DESC%', $config['description'], $data);
		$data = str_replace('%COST%', $config['upgradeCost'], $data);
		$params = '';
		if (isset($config['type'])) {
			$params .= '	RS_EngineType = ' . $config['type'] . '
';
		}
		if (isset($config['propellants'])) {
			$params .= '	RS_Propellants = ' . $config['propellants'] . '
';
		}
		if (isset($config['maxThrust'])) {
			$params .= '	RS_MaxThrust = ' . $config['maxThrust'] . '
';
		}
		if (isset($config['ispVac'])) {
			$params .= '	RS_ISPVac = ' . $config['ispVac'] . '
';
		}
		if (isset($config['ispSL'])) {
			$params .= '	RS_ISPSL = ' . $config['ispSL'] . '
';
		}
		if (isset($config['ignitions'])) {
			$params .= '	RS_Ignitions = ' . $config['ignitions'] . '
';
		}
		if (isset($config['ullage'])) {
			$params .= '	RS_Ullage = ' . $config['ullage'] . '
';
		}
		if (isset($config['pressureFed'])) {
			$params .= '	RS_PressureFed = ' . $config['pressureFed'] . '
';
		}
		$data = str_replace('%PARAMS%', $params, $data);
		if ($config['type'] == 'SRB') {
			$data = str_replace('%ICON%', 'MassiveBooster', $data);
		}
		else if ($config['type'] == 'nuclear') {
			$data = str_replace('%ICON%', 'nuclearEngine', $data);
		}
		else {
			$data = str_replace('%ICON%', 'liquidEngine', $data);
		}
		$cfg .= $data;
		if ($upgradeIcons)
		{
			foreach ($upgradeIcons as $upgradeIcon)
			{
				$needs = $upgradeIcon['needs'];
				if (is_null($needs)) 
				{
					$needs = '';
				}
				else {
					$needs = ':NEEDS[' . $needs . ']';
				}
				$data = str_replace('%CONFIG%',$configName, $partUpgradeIconTemplate);
				$data = str_replace('%NEEDS%', $needs, $data);
				$data = str_replace('%ICON%', $upgradeIcon['partName'], $data);
				$cfg .= $data;
			}
		}
	}
	print $configName . "\t\t" . $config['title'] . "\r\n";
	file_put_contents($outputDir . $fileNames[1] . '.cfg', $cfg);
}

if ($locAddLines) {
	file_put_contents($locEnFile, $locAddLines, FILE_APPEND);
}

function config_param($param, $RSParam)
{
	global $config, $configName, $engineCFGParamTemplate;

	$fragment = '';

	if (isset($config[$param]))
	{
		$fragment = $engineCFGParamTemplate;
		$fragment = str_replace('%CONFIG%', $configName, $fragment);
		$fragment = str_replace('%RS_PARAM%', $RSParam, $fragment);
		$fragment = str_replace('%PARAM%', $param, $fragment);
	}
	return $fragment;
}

function b9ps_config_param($param, $RSParam)
{
	global $config, $configName, $engineB9PSParamTemplate;

	$fragment = '';

	if (isset($config[$param]))
	{
		$fragment = $engineB9PSParamTemplate;
		$fragment = str_replace('%CONFIG%', $configName, $fragment);
		$fragment = str_replace('%RS_PARAM%', $RSParam, $fragment);
		$fragment = str_replace('%PARAM%', $param, $fragment);
	}
	return $fragment;
}
?>
