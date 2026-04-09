"""
StudentsHub — FastAPI AI Service
Phase 4 scaffold — placeholder endpoints, full implementation in Phase 4.
"""

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional
import os

app = FastAPI(
    title="StudentsHub AI Service",
    description="Intent classification, RAG, and agentic AI layer for StudentsHub",
    version="0.1.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Restrict in production
    allow_methods=["*"],
    allow_headers=["*"],
)


# ----------------------------------------------------------
# Health check
# ----------------------------------------------------------
@app.get("/health")
def health():
    return {"status": "ok", "service": "studentshuib-ai", "version": "0.1.0"}


# ----------------------------------------------------------
# Phase 4 stubs — will be fully implemented
# ----------------------------------------------------------

class IntentRequest(BaseModel):
    message: str
    student_id: Optional[str] = None
    session_id: Optional[str] = None

class IntentResponse(BaseModel):
    intent: str
    confidence: float
    suggested_form_type: Optional[str] = None
    response: str


@app.post("/api/v1/intent", response_model=IntentResponse)
async def classify_intent(req: IntentRequest):
    """
    Phase 4: Classify student message intent and route to appropriate agent.
    Currently returns a placeholder response.
    """
    # TODO Phase 4: implement intent classification with OpenAI/Anthropic
    return IntentResponse(
        intent="information",
        confidence=0.0,
        suggested_form_type=None,
        response="AI service not yet active. This endpoint will be fully implemented in Phase 4.",
    )


class KnowledgeQuery(BaseModel):
    query: str
    top_k: int = 5

@app.post("/api/v1/knowledge/search")
async def knowledge_search(req: KnowledgeQuery):
    """
    Phase 4: RAG search over university knowledge base using pgvector.
    """
    # TODO Phase 4: embed query, search pgvector, return ranked chunks
    return {"results": [], "message": "Knowledge base search not yet active (Phase 4)."}


class EligibilityCheck(BaseModel):
    student_id: str
    form_type_slug: str

@app.post("/api/v1/eligibility/check")
async def check_eligibility(req: EligibilityCheck):
    """
    Phase 4: Check if a student is eligible for a specific service.
    """
    # TODO Phase 4: check against academic rules, outstanding dues, clearance status
    return {"eligible": True, "reasons": [], "message": "Eligibility checking not yet active (Phase 4)."}


class FormPrefill(BaseModel):
    student_id: str
    form_type_slug: str

@app.post("/api/v1/form/prefill")
async def prefill_form(req: FormPrefill):
    """
    Phase 4: Pre-fill form fields using student profile data.
    """
    # TODO Phase 4: pull student profile, suggest field values
    return {"prefilled_fields": {}, "message": "Form pre-fill not yet active (Phase 4)."}
