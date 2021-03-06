<?php

declare(strict_types=1);

// Generell funktions
require_once __DIR__ . '/../libs/_traits.php';

// Blink Home Sync Modul
class BlinkHomeSyncModule extends IPSModule
{
    // Helper Traits
    use DebugHelper;
    use EventHelper;
    use VariableHelper;

    // Schedule constant
    private const BLINK_SCHEDULE_RECORDING_OFF = 1;
    private const BLINK_SCHEDULE_RECORDING_ON = 2;
    private const BLINK_SCHEDULE_RECORDING_IDENT = 'circuit_recording';
    private const BLINK_SCHEDULE_RECORDING_SWITCH = [
        self::BLINK_SCHEDULE_RECORDING_OFF => ['Inaktive', 0xFF0000, "IPS_RequestAction(\$_IPS['TARGET'], 'schedule_recording', \$_IPS['ACTION']);"],
        self::BLINK_SCHEDULE_RECORDING_ON  => ['Aktive', 0x00FF00, "IPS_RequestAction(\$_IPS['TARGET'], 'schedule_recording', \$_IPS['ACTION']);"],
    ];

    /**
     * Overrides the internal IPS_Create($id) function
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        // Connect to client
        $this->ConnectParent('{AF126D6D-83D1-44C2-6F61-96A4BB7A0E62}');
        // Device
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyString('NetworkID', '');
        $this->RegisterPropertyString('DeviceType', 'null');
        $this->RegisterPropertyString('DeviceModel', 'null');
        $this->RegisterPropertyBoolean('CheckRecording', false);
        // Schedule
        $this->RegisterPropertyInteger('RecordingSchedule', 0);
    }

    /**
     * Overrides the internal IPS_Destroy($id) function
     */
    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * Overrides the internal IPS_ApplyChanges($id) function
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        // Maintain variables
        $recording = $this->ReadPropertyBoolean('CheckRecording');
        $this->MaintainVariable('recording', $this->Translate('Recording'), vtBoolean, '~Switch', 0, $recording);
        if ($recording) {
            //$this->SetValueBoolean('recording', false);
            $this->EnableAction('recording');
        }
    }

    /**
     * RequestAction.
     *
     *  @param string $ident Ident.
     *  @param string $value Value.
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        switch ($ident) {
            case 'create_schedule':
                $this->CreateScheduleRecording();
                break;
            case 'schedule_recording':
                $this->ScheduleRecording($value);
                break;
            case 'recording':
                if ($value) {
                    $this->Arm();
                } else {
                    $this->Disarm();
                }
                break;
            case 'network':
                $this->Network();
                break;
            case 'sync_module':
                $this->SyncModule();
                break;
            default:
                break;
        }
        //return true;
    }

    /**
     * Arm the given network - that is, start recording/reporting motion events for enabled cameras.
     *
     * BHS_Arm();
     */
    public function Arm()
    {
        // Params
        $param = ['NetworkID' => $this->ReadPropertyString('NetworkID')];
        // Request
        $response = $this->RequestDataFromParent('arm', $param);
        // Debug
        $this->SendDebug(__FUNCTION__, $response);
        // Result?
        if ($response !== false) {
            $this->SetValueBoolean('recording', true);
            //echo $this->Translate('Call was successfull!');
            return true;
        } else {
            echo $this->Translate('Call was not successfull!');
            return false;
        }
    }

    /**
     * Disarm the given network - that is, stop recording/reporting motion events for enabled cameras.
     *
     * BHS_Disarm();
     */
    public function Disarm()
    {
        // Params
        $param = ['NetworkID' => $this->ReadPropertyString('NetworkID')];
        // Request
        $response = $this->RequestDataFromParent('disarm', $param);
        // Debug
        $this->SendDebug(__FUNCTION__, $response);
        // Result?
        if ($response !== false) {
            $this->SetValueBoolean('recording', false);
            //echo $this->Translate('Call was successfull!');
            return true;
        } else {
            echo $this->Translate('Call was not successfull!');
            return false;
        }
    }

    /**
     * Display Network Information
     */
    private function Network()
    {
        // Debug
        $this->SendDebug(__FUNCTION__, 'Obtain network information.');
        // Network
        $network = $this->ReadPropertyString('NetworkID');
        // Request data
        $response = $this->RequestDataFromParent('homescreen');
        // Result?
        if ($response !== false) {
            $params = json_decode($response, true);
            $this->SendDebug(__FUNCTION__, $params);
            if (isset($params['networks'])) {
                foreach ($params['networks'] as $param) {
                    if ($param['id'] == $network) {
                        $this->SendDebug(__FUNCTION__, 'FOUND');
                        // Prepeare Info
                        $format = $this->Translate("Name: %s\nTimezone: %s\nDaylight savings time: %s\nArmed: %s\nLocal save: %s\nCreated at: %s\nUpdated at: %s");
                        $info = sprintf(
                            $format,
                            $param['name'],
                            $param['time_zone'],
                            $param['dst'] ? $this->Translate('ON') : $this->Translate('OFF'),
                            $param['armed'] ? $this->Translate('ON') : $this->Translate('OFF'),
                            $param['lv_save'] ? $this->Translate('ON') : $this->Translate('OFF'),
                            strftime('%a, %d.%b, %H:%M', strtotime($param['created_at'])),
                            strftime('%a, %d.%b, %H:%M', strtotime($param['updated_at']))
                        );
                        echo $info;
                        break;
                    }
                }
            }
        } else {
            echo $this->Translate('Call was not successfull!');
        }
    }

    /**
     * Display Sync Module Information
     */
    private function SyncModule()
    {
        // Debug
        $this->SendDebug(__FUNCTION__, 'Obtain sync module information.');
        // Sync Module Device
        $device = $this->ReadPropertyString('DeviceID');
        // Request data
        $response = $this->RequestDataFromParent('homescreen');
        // Result?
        if ($response !== false) {
            $params = json_decode($response, true);
            $this->SendDebug(__FUNCTION__, $params);
            if (isset($params['sync_modules'])) {
                foreach ($params['sync_modules'] as $param) {
                    if ($param['id'] == $device) {
                        // Prepeare Info
                        $format = $this->Translate("Name: %s\nStatus: %s\nSerial: %s\nFirmware: %s\nType: %s\nSubtype: %s\nWifi strength: %d\nNetwork ID: %s\nOnboarded: %s\nEnable temparature alerts: %s\nLocal storage enabled: %s\nLocal storage compatible: %s\nLocal storage status: %s\nLast heartbeat at: %s\nCreated at: %s\nUpdated at: %s");
                        $info = sprintf(
                            $format,
                            $param['name'],
                            $param['status'],
                            $param['serial'],
                            $param['fw_version'],
                            $param['type'],
                            $param['subtype'],
                            $param['wifi_strength'],
                            $param['network_id'],
                            $param['onboarded'] ? $this->Translate('YES') : $this->Translate('NO'),
                            $param['enable_temp_alerts'] ? $this->Translate('YES') : $this->Translate('NO'),
                            $param['local_storage_enabled'] ? $this->Translate('YES') : $this->Translate('NO'),
                            $param['local_storage_compatible'] ? $this->Translate('YES') : $this->Translate('NO'),
                            $param['local_storage_status'],
                            strftime('%a, %d.%b, %H:%M', strtotime($param['last_hb'])),
                            strftime('%a, %d.%b, %H:%M', strtotime($param['created_at'])),
                            strftime('%a, %d.%b, %H:%M', strtotime($param['updated_at']))
                        );
                        echo $info;
                        break;
                    }
                }
            }
        } else {
            echo $this->Translate('Call was not successfull!');
        }
    }

    /**
     * Weekly Schedule event
     *
     * @param integer $value Action value (ON=2, OFF=1)
     */
    private function ScheduleRecording(int $value)
    {
        $schedule = $this->ReadPropertyInteger('RecordingSchedule');
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value . ',Schedule: ' . $schedule);
        if ($schedule == 0) {
            $this->SendDebug(__FUNCTION__, 'Schedule not linked!');
            // nothing todo
            return;
        }
        // Is Activate OFF
        if ($value == self::BLINK_SCHEDULE_RECORDING_OFF) {
            $this->SendDebug(__FUNCTION__, 'OFF: Disarm recording!');
            // Stop Recording
            $this->Disarm();
        }
        if ($value == self::BLINK_SCHEDULE_RECORDING_ON) {
            $this->SendDebug(__FUNCTION__, 'ON: Arm recording!');
            // Start Recording
            $this->Arm();
        }
    }

    /**
     * Create week schedule for snapshots
     *
     */
    private function CreateScheduleRecording()
    {
        $eid = $this->CreateWeeklySchedule($this->InstanceID, $this->Translate('Schedule recording'), self::BLINK_SCHEDULE_RECORDING_IDENT, self::BLINK_SCHEDULE_RECORDING_SWITCH, -1);
        if ($eid !== false) {
            $this->UpdateFormField('RecordingSchedule', 'value', $eid);
        }
    }

    /**
     * Returns the ascending list of category names for a given category id
     *
     * @param string $endpoint API endpoint request.
     * @return string Result of the API call.
     */
    private function RequestDataFromParent(string $endpoint, array $params = [])
    {
        return $this->SendDataToParent(json_encode([
            'DataID'      => '{83027B09-C481-91E7-6D24-BF49AA871452}',
            'Endpoint'    => $endpoint,
            'Params'      => $params,
        ]));
    }
}
