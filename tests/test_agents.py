import unittest
from agents.orchestrator import OrchestratorAgent

class TestAgents(unittest.TestCase):
    def test_orchestrator_initialization(self):
        orchestrator = OrchestratorAgent()
        self.assertEqual(orchestrator.name, "OrchestratorAgent")
        self.assertIsNotNone(orchestrator.scout)
        self.assertIsNotNone(orchestrator.judge)

if __name__ == '__main__':
    unittest.main()
