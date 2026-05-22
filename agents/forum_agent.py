import logging

logger = logging.getLogger(__name__)

class ForumAgent:
    """
    ForumAgent generates helpful, factual comments for deals.
    MUST strictly follow AGENTS.md rules (No FOMO, no fake verification).
    """
    def __init__(self):
        self.name = "ForumAgent"

    def generate_comment(self, deal: dict, similar_deals: list) -> str:
        """Generate a factual, useful comment using AI."""
        logger.info(f"[{self.name}] Generating factual comment for: {deal.get('title')}")
        
        # AGENTS.md compliance:
        # - AI Label required
        # - Useful info only (price comparison, alternatives)
        # - NO FOMO or fake verified statements.
        
        comment = f"🤖 Agent_{self.name}: "
        comment += f"The current price is ${deal['price']}, which is a {((deal['original_price'] - deal['price'])/deal['original_price'])*100:.0f}% discount off the original ${deal['original_price']}. "
        
        if similar_deals:
            comment += "Similar deals in our database: "
            alts = [f"{d.get('title')} (AI Score: {d.get('score', 0):.2f})" for d in similar_deals[:2]]
            comment += ", ".join(alts) + "."
            
        logger.info(f"[{self.name}] Comment generated successfully.")
        return comment
