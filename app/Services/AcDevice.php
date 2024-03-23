<?php

namespace App\Services;

use App\Services\Enum\TemperatureUnit;
use Illuminate\Support\Facades\Log;

class AcDevice
{
    public string $id;
    public string $name;
    public TemperatureUnit $temperatureUnit;
    public int $temperature;
    public int $currentTemperature;
    public string $mode;
    public array $raw;

    public array $modeOptions;

    public function __construct(array $connectLifeAcDeviceStatus)
    {
        $this->id = $connectLifeAcDeviceStatus['puid'];
        $this->name = $connectLifeAcDeviceStatus['deviceNickName'];
        $this->temperatureUnit = TemperatureUnit::from(0);
        $this->temperature = (int)$connectLifeAcDeviceStatus['statusList']['t_temp'];
        $this->currentTemperature = (int)$connectLifeAcDeviceStatus['statusList']['f_water_tank_temp'];

        $deviceConfiguration = $this->getDeviceConfiguration($connectLifeAcDeviceStatus['deviceFeatureCode']);

        $this->modeOptions = $this->extractMetadata($deviceConfiguration, 't_work_mode');

        $this->mode = $connectLifeAcDeviceStatus['statusList']['t_power'] === '0'
            ? 'off'
            : array_search($connectLifeAcDeviceStatus['statusList']['t_work_mode'], $this->modeOptions);

        $this->raw = $connectLifeAcDeviceStatus;
    }

    private function extractMetadata(
        array  $connectLifeAcDeviceMetadata,
        string $metadataKey
    ): array
    {
        $metadataOptions = [];

        foreach ($connectLifeAcDeviceMetadata[$metadataKey] as $key => $value) {
            $modeKey = str_replace(' ', '_', strtolower($value));
            $metadataOptions[$modeKey] = (string)$key;
        }

        return $metadataOptions;
    }

    public function toConnectLifeApiPropertiesArray(): array
    {
        $data = [
            't_power' => $this->mode === 'off' ? 0 : 1,
            't_temp_type' => $this->temperatureUnit->value,
            't_temp' => $this->temperature,
            't_beep' => (int)env('BEEPING', 1)
        ];

        if ($this->mode !== 'off') {
            $data['t_work_mode'] = (int)$this->modeOptions[$this->mode];
        }

        return $data;
    }

    public function toHomeAssistantDiscoveryArray(): array
    {
        $data = [
            'name' => $this->name ?? $this->id,
            'unique_id' => $this->id,
            'modes' => $this->getHaModesSubset(),
#            'fan_modes' => array_keys($this->fanSpeedOptions),
#            'swing_modes' => array_keys($this->swingOptions),
            'payload_on' => '1',
            'payload_off' => '0',
            'power_command_topic' => "$this->id/ac/power/set",
            'mode_command_topic' => "$this->id/ac/mode/set",
            'mode_state_topic' => "$this->id/ac/mode/get",
            'temperature_command_topic' => "$this->id/ac/temperature/set",
            'temperature_state_topic' => "$this->id/ac/temperature/get",
            'current_temperature_topic' => "$this->id/ac/current-temperature/get",
            'json_attributes_topic' => "$this->id/ac/attributes/get",
            'precision' => 1,
            'max_temp' => $this->temperatureUnit === TemperatureUnit::celsius ? 65 : 149,
            'min_temp' => $this->temperatureUnit === TemperatureUnit::celsius ? 20 : 68,
            'device' => [
                'identifiers' => [$this->id],
                'manufacturer' => 'Connectlife',
                'model' => ($this->raw['deviceTypeCode'] ?? '') . '-' . ($this->raw['deviceFeatureCode'] ?? '')
            ]
        ];

        return $data;
    }

    private function getHaModesSubset(): array
    {
        $options = array_keys($this->modeOptions);
        array_push($options, 'off');

        return $options;
    }

    private function getDeviceConfiguration(string $deviceTypeCode): array
    {
        $configuration = json_decode(env('DEVICES_CONFIG', '[]'), true);

        if (isset($configuration[$deviceTypeCode])) {
            return $configuration[$deviceTypeCode];
        }

        Log::debug('Device configuration not found, using default.');

        $defaultConfiguration = '{"t_work_mode":{"8":"eco","9":"heat_pump","11":"performance","12":"electric"}}';

        return json_decode($defaultConfiguration, true);
    }
}
