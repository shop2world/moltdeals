# MoltDeals: The AI That Earns You Money 🦞💰

**Submission for the Google Cloud Rapid Agent Hackathon (2026)**

MoltDeals is a multi-agent AI system built to autonomously find, evaluate, monetize, and post deals on the internet. It leverages the **Google Cloud Agent Development Kit (ADK)** and integrates with **MongoDB Atlas Vector Search** and **Dynatrace**.

> **Note to Judges:** This repository contains the AI Agent Engine. The frontend is live at [moltdeals.net](https://moltdeals.net), but this engine runs in `DRY_RUN` mode by default to protect our live database during your testing.

## 🌟 Hackathon Partner Integrations

1. **MongoDB (Primary Track)**:
   - Uses the official **MongoDB MCP Server**.
   - Deal data is embedded using Vertex AI and stored in MongoDB Atlas.
   - **Atlas Vector Search** is used to find similar deals to generate context-aware AI forum comments.
2. **Dynatrace**:
   - Instrumented with OpenTelemetry to trace agent executions, token usage, and pipeline latency.

## 🧠 The Agent Architecture

The system uses a 5-step ADK Orchestrator pipeline:

1. **ScoutAgent**: Crawls Reddit and RSS feeds to discover raw deals.
2. **JudgeAgent**: Uses **Gemini 2.5 Flash** to evaluate the deal, assigning a 1-100 score based on discount depth and brand value.
3. **AffiliateAgent**: Automatically generates monetization (affiliate) links for the deals.
4. **MongoDB Vector Search**: Retrieves historically similar deals from Atlas.
5. **ForumAgent**: Uses Gemini to write a helpful, factual comment (comparing prices/alternatives) without using FOMO or fake reviews.

## 🚀 Quickstart (Run the Demo)

You can run the full multi-agent pipeline locally in just a few steps.

1. Install dependencies:
   ```bash
   pip install -r requirements.txt
   ```
2. Copy the environment file (no actual keys needed for the mock demo):
   ```bash
   cp .env.example .env
   ```
3. Run the orchestrator:
   ```bash
   python demo/demo_run.py
   ```

Check `demo/sample_output.json` to see the generated deals and AI comments!

## 🛡️ Safety & Rules
This AI strictly adheres to the MoltDeals `AGENTS.md` guidelines:
- **No FOMO**: Never uses phrases like "sell out fast".
- **Factual Only**: Comments are generated based on MongoDB Vector Search historical data.
- **Auto-Monetization**: Links are reliably converted.
