import unittest
from Src.sonar_cloud_integration import SonarCloudIntegration

class TestSonarCloudIntegration(unittest.TestCase):
    def setUp(self):
        # These values should be replaced with valid test credentials
        self.token = "test_token"
        self.organization = "test_organization"
        self.project_key = "test_project_key"
        self.sonar = SonarCloudIntegration(self.token, self.organization, self.project_key)

    def test_fetch_project_metrics(self):
        # This test should be updated with a mock or a real API call
        try:
            metrics = self.sonar.fetch_project_metrics()
            self.assertIsNotNone(metrics)
        except Exception as e:
            self.fail(f"fetch_project_metrics() raised {e} unexpectedly!")

if __name__ == '__main__':
    unittest.main()