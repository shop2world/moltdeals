import logging

logger = logging.getLogger(__name__)

class AffiliateAgent:
    """
    AffiliateAgent is responsible for taking a raw URL and generating
    a monetization affiliate link (e.g. Amazon Associates).
    """
    def __init__(self):
        self.name = "AffiliateAgent"
        self.affiliate_tag = "moltdeals-20"

    def monetize_url(self, deal: dict) -> dict:
        """Convert standard URL to affiliate URL."""
        logger.info(f"[{self.name}] Generating affiliate link for: {deal.get('title')}")
        
        original_url = deal.get('url', '')
        
        if "amazon.com" in original_url:
            # Simple mock of Amazon affiliate link generation
            if "?" in original_url:
                affiliate_url = f"{original_url}&tag={self.affiliate_tag}"
            else:
                affiliate_url = f"{original_url}?tag={self.affiliate_tag}"
            
            deal['affiliate_url'] = affiliate_url
            logger.info(f"[{self.name}] Monetized: {affiliate_url}")
        else:
            deal['affiliate_url'] = original_url
            logger.info(f"[{self.name}] Could not monetize, kept original URL.")
            
        return deal
