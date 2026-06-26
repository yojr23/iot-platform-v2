import unittest
from datetime import datetime
from unittest.mock import Mock, patch

import script_datos


class ScriptDatosTest(unittest.TestCase):
    def setUp(self):
        script_datos.SENSOR_LAST_VALUES.clear()

    def test_get_api_key_prefers_environment_variables(self):
        with patch.dict('os.environ', {'IOT_API_KEY': 'iot-key', 'API_KEY': 'legacy-key'}, clear=False):
            self.assertEqual('iot-key', script_datos.get_api_key())

        with patch.dict('os.environ', {'IOT_API_KEY': '', 'API_KEY': 'legacy-key'}, clear=False):
            self.assertEqual('legacy-key', script_datos.get_api_key())

    def test_require_api_key_exits_when_no_key_available(self):
        with patch.object(script_datos, 'DEFAULT_API_KEY', ''), patch.dict('os.environ', {'IOT_API_KEY': '', 'API_KEY': ''}, clear=False), patch('builtins.print'):
            with self.assertRaises(SystemExit) as ctx:
                script_datos.require_api_key()

        self.assertEqual(1, ctx.exception.code)

    def test_simulate_value_returns_in_range_when_random_branch_is_normal(self):
        sensor = {'id': 11, 'sensor_type': {'min_range': 10, 'max_range': 20}}
        script_datos.SENSOR_LAST_VALUES[sensor['id']] = 15.0

        with patch('script_datos.random.random', return_value=0.9), patch('script_datos.random.uniform', return_value=0.2) as uniform_mock:
            value = script_datos.simulate_value(sensor)

        self.assertEqual(15.2, value)
        uniform_mock.assert_called_once_with(-0.2, 0.2)

    def test_simulate_value_can_generate_below_minimum_for_alerts(self):
        sensor = {'id': 12, 'sensor_type': {'min_range': 10, 'max_range': 20}}
        script_datos.SENSOR_LAST_VALUES[sensor['id']] = 15.0

        with patch('script_datos.random.random', side_effect=[0.0, 0.3]), patch('script_datos.random.uniform', return_value=1.0):
            value = script_datos.simulate_value(sensor)

        self.assertEqual(9.0, value)

    def test_send_sensor_data_publishes_expected_mqtt_payload(self):
        sensor = {
            'id': 7,
            'device_id': 3,
            'device': {
                'serial_number': 'LAB_POSTGRADO_NODO_01',
                'lab': {'name': 'Laboratorio Posgrado Quimica - UNAB'},
            },
            'sensor_type': {'name': 'Temperatura', 'unit': 'C'},
        }

        publish_mock = Mock()

        fixed_now = datetime(2026, 4, 26, 12, 0, 0)

        with patch('script_datos.simulate_value', return_value=33.25), patch('builtins.print'):
            script_datos.send_sensor_data(
                sensor,
                api_key='api-key-123',
                now_fn=lambda: fixed_now,
                publish_fn=publish_mock,
            )

        publish_mock.assert_called_once()
        args, kwargs = publish_mock.call_args
        self.assertEqual('iot/lab_postgrado_nodo_01/readings', args[0])
        self.assertIn('"sensors": {"temperature": {"value": 33.25', kwargs['payload'])
        self.assertEqual(script_datos.MQTT_QOS, kwargs['qos'])
        self.assertEqual(script_datos.MQTT_HOST, kwargs['hostname'])
        self.assertEqual(script_datos.MQTT_PORT, kwargs['port'])


if __name__ == '__main__':
    unittest.main()
