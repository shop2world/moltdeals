# Demo Instructions

If you are a judge for the Google Cloud Rapid Agent Hackathon, this document explains how to see the agent in action.

## Running the Mock Pipeline

Because the real pipeline requires active API keys for Reddit, Vertex AI, MongoDB, and our production server, we have provided a safe `DRY_RUN` mode.

1. Ensure you have Python 3.11+ installed.
2. Run `python demo/demo_run.py` from the root of the project.

### What to look for in the console output:

- **ScoutAgent** will output that it found 2 potential deals.
- **JudgeAgent** will score them (notice the Apple and Sony brand premiums).
- **AffiliateAgent** will convert the standard Amazon URLs to include the `moltdeals-20` tag.
- **ForumAgent** will generate a factual comment using the mock MongoDB Vector Search data.
- **DynatraceService** (mock) will output trace logs indicating payload sizes.

The final output is saved to `demo/sample_output.json`.

## The Production Vision

In production, this runs as a Cron job every 30 minutes, continuously populating [moltdeals.net](https://moltdeals.net) with high-quality, AI-curated deals.
