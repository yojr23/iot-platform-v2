import unittest
from datetime import datetime
from unittest.mock import Mock, patch

import script_datos


class ScriptDatosTest(unittest.TestCase):
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
        sensor = {'sensor_type': {'min_range': 10, 'max_range': 20}}

        with patch('script_datos.random.random', return_value=0.9), patch('script_datos.random.uniform', return_value=15.5) as uniform_mock:
            value = script_datos.simulate_value(sensor)

        self.assertEqual(15.5, value)
        uniform_mock.assert_called_once_with(10.1, 19.9)

    def test_simulate_value_can_generate_below_minimum_for_alerts(self):
        sensor = {'sensor_type': {'min_range': 10, 'max_range': 20}}

        with patch('script_datos.random.random', side_effect=[0.05, 0.3]), patch('script_datos.random.uniform', return_value=2.0):
            value = script_datos.simulate_value(sensor)

        self.assertEqual(8.0, value)

    def test_send_sensor_data_posts_expected_payload(self):
        sensor = {
            'id': 7,
            'sensor_type': {'name': 'Temperatura', 'unit': 'C'}
        }

        fake_response = Mock(status_code=201)
        post_mock = Mock(return_value=fake_response)

        fixed_now = datetime(2026, 4, 26, 12, 0, 0)

        with patch('script_datos.simulate_value', return_value=33.25), patch('builtins.print'):
            script_datos.send_sensor_data(
                sensor,
                api_key='api-key-123',
                now_fn=lambda: fixed_now,
                post_fn=post_mock,
            )

        post_mock.assert_called_once()
        args, kwargs = post_mock.call_args
        self.assertEqual('http://127.0.0.1:8000/api/sensors/7/readings', args[0])
        self.assertEqual(33.25, kwargs['json']['value'])
        self.assertEqual('2026-04-26 12:00:00', kwargs['json']['reading_time'])
        self.assertEqual('api-key-123', kwargs['json']['api_key'])
        self.assertEqual(5, kwargs['timeout'])


if __name__ == '__main__':
    unittest.main()
