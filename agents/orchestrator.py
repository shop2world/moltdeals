import logging
import json
from typing import List

from agents.scout_agent import ScoutAgent
from agents.judge_agent import JudgeAgent
from agents.affiliate_agent import AffiliateAgent
from agents.forum_agent import ForumAgent
from services.mongodb_service import MongoDBService
from services.moltdeals_api import MoltDealsAPI
from services.dynatrace_service import DynatraceService

logger = logging.getLogger(__name__)

class OrchestratorAgent:
    """
    The Orchestrator coordinates the Multi-Agent workflow using ADK patterns.
    Workflow: Scout -> Judge -> Affiliate -> DB/Search -> Forum -> Post
    """
    def __init__(self):
        self.name = "OrchestratorAgent"
        
        # Initialize sub-agents
        self.scout = ScoutAgent()
        self.judge = JudgeAgent()
        self.affiliate = AffiliateAgent()
        self.forum = ForumAgent()
        
        # Initialize services
        self.db = MongoDBService()
        self.api = MoltDealsAPI() # Defaults to DRY_RUN=True
        self.tracer = DynatraceService()

    def run_pipeline(self):
        """Execute the full deal-finding pipeline."""
        logger.info(f"========== Starting {self.name} Pipeline ==========")
        
        # 1. Scout for deals
        raw_deals = self.scout.discover_deals()
        self.tracer.trace_agent_execution(self.scout.name, {"deals_found": len(raw_deals)})
        
        processed_deals = []
        
        for raw_deal in raw_deals:
            logger.info(f"--- Processing Deal: {raw_deal['title']} ---")
            
            # 2. Judge the deal
            judged_deal = self.judge.evaluate_deal(raw_deal)
            self.tracer.trace_agent_execution(self.judge.name, {"score": judged_deal.get('ai_score')})
            
            # Filter bad deals
            if judged_deal['ai_score'] < 60:
                logger.info("Deal score too low. Skipping.")
                continue
                
            # 3. Monetize
            monetized_deal = self.affiliate.monetize_url(judged_deal)
            self.tracer.trace_agent_execution(self.affiliate.name, {"monetized": True})
            
            # 4. Save & Vector Search (MongoDB Atlas MCP integration point)
            # Create a mock embedding for vector search
            mock_embedding = [0.1] * 768 
            similar_deals = self.db.vector_search_similar_deals(mock_embedding, limit=2)
            
            # 5. Generate Forum Comment
            comment = self.forum.generate_comment(monetized_deal, similar_deals)
            self.tracer.trace_agent_execution(self.forum.name, {"comment_length": len(comment)})
            
            # 6. Post to API (Dry Run protected)
            success_post = self.api.post_deal(
                title=monetized_deal['title'],
                url=monetized_deal['affiliate_url'],
                price=monetized_deal['price'],
                original_price=monetized_deal['original_price'],
                category=monetized_deal['category'],
                score=monetized_deal['ai_score']
            )
            
            if success_post:
                # Assuming post_deal returns ID 999 for dry_run
                self.api.post_forum_comment(999, comment)
                
            # Save to MongoDB
            self.db.save_deal(monetized_deal)
            
            processed_deals.append(monetized_deal)
            
        logger.info(f"========== Pipeline Complete. Processed {len(processed_deals)} deals. ==========")
        return processed_deals
