import logging
import os

logger = logging.getLogger(__name__)

class JudgeAgent:
    """
    JudgeAgent evaluates deals using Vertex AI / Gemini.
    It assigns a score from 1-100 and provides a justification.
    """
    def __init__(self, project_id: str = None):
        self.name = "JudgeAgent"
        self.project_id = project_id or os.getenv("GCP_PROJECT_ID")
        
        # Placeholder for Vertex AI Initialization
        # import vertexai
        # vertexai.init(project=self.project_id, location="us-central1")
        # from vertexai.generative_models import GenerativeModel
        # self.model = GenerativeModel("gemini-2.5-flash")

    def evaluate_deal(self, deal: dict) -> dict:
        """Evaluate a deal using Gemini."""
        logger.info(f"[{self.name}] Evaluating deal: {deal.get('title')}")
        
        # In reality, this calls Gemini. Mocking for now to avoid actual API calls during setup.
        # prompt = f"Evaluate this deal: {deal}. Give a score 1-100."
        # response = self.model.generate_content(prompt)
        
        # Simulate AI evaluation logic
        discount_percentage = ((deal['original_price'] - deal['price']) / deal['original_price']) * 100
        
        score = min(int(50 + (discount_percentage * 1.5)), 99)
        if "Sony" in deal.get('title', '') or "Apple" in deal.get('title', ''):
            score = min(score + 10, 99) # Brand premium
            
        deal['ai_score'] = score
        deal['ai_reasoning'] = f"Good discount of {discount_percentage:.1f}%. High quality brand."
        
        logger.info(f"[{self.name}] Assigned score {score} to {deal.get('title')}")
        return deal
