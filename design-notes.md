# Design Notes - Deep Research Agent

## Core Design Philosophy

### Diffusion-Inspired Architecture
The system treats report generation like image diffusion:

```
Noise (gaps, placeholders) → Iterative Refinement → Clean Signal (polished report)
```

**Key Insight**: Research reports naturally start "noisy" (incomplete, unverified) and require iterative refinement. This maps perfectly to diffusion algorithms where each step reduces noise.

**Implementation**:
1. **T=0 (Max Noise)**: Initial draft with `[NEEDS RESEARCH: topic]` markers
2. **T=1...N (Denoising)**: Each iteration:
   - Identify gaps (supervisor analysis)
   - Gather information (researcher agents)
   - Integrate findings (refine_draft_report)
   - Evaluate quality (judge model)
   - Critique for flaws (red team)
3. **T=Final (Clean)**: Quality threshold met, finalize report

### Self-Evolution Through Quality Feedback
The system creates a feedback loop where quality evaluation directly influences behavior:

```
Draft → Evaluate (1-10) → If <7: needs_quality_repair=True → Force more research
```

This is similar to RLHF but operates at runtime, not training time.

## Graph Architecture

### Master Graph Flow
```
START
  │
  ▼
clarify_with_user ──[needs_clarification]──► END (return questions)
  │
  │ [no clarification needed]
  ▼
write_research_brief
  │
  ▼
write_draft_report
  │
  ▼
supervisor_subgraph (loop until complete)
  │
  ▼
final_report_generation
  │
  ▼
END
```

### Supervisor Subgraph (Denoising Engine)
```
START
  │
  ▼
supervisor (brain) ──[Command]──► supervisor_tools (hands)
  │                                      │
  │◄─────────────────────────────────────┤
  │                                      │
  │                              [parallel fan-out]
  │                                 │         │
  │                                 ▼         ▼
  │                            red_team  context_pruner
  │                                 │         │
  │◄────────────────────────────────┴─────────┘
  │
  │ [ResearchComplete or max_iterations]
  ▼
END
```

### Researcher Subgraph (Think-Act-Compress)
```
START
  │
  ▼
llm_call ──[has tool_calls]──► tool_node
  │    ◄─────────────────────────┘
  │
  │ [no tool_calls or max_calls reached]
  ▼
compress_research
  │
  ▼
END
```

## State Design

### State Hierarchy
```
AgentInputState (user provides)
       │
       ▼
AgentState (main workflow)
       │
       ├──► SupervisorState (denoising loop)
       │           │
       │           └──► ResearcherState (per task)
       │                      │
       │                      ▼
       │               ResearcherOutputState
       │
       ▼
AgentState (with final_report)
```

### State Reducers
For fields that accumulate across iterations, use `Annotated` with `add`:

```python
from typing import Annotated
from operator import add

class SupervisorState(BaseModel):
    # Accumulates: each node adds to the list
    knowledge_base: Annotated[list[Fact], add] = Field(default_factory=list)
    supervisor_messages: Annotated[list[BaseMessage], add] = Field(default_factory=list)
    
    # Replaces: each update overwrites
    draft_report: str = ""
    needs_quality_repair: bool = False
```

### Knowledge Base Design
Raw notes are ephemeral; structured facts are permanent:

```
Raw Notes (buffer)              Knowledge Base (permanent)
┌─────────────────┐            ┌─────────────────────────┐
│ Search result 1 │            │ Fact 1                  │
│ Search result 2 │  ──────►   │   content: "..."        │
│ Search result 3 │  extract   │   source_url: "..."     │
│ (cleared after) │            │   confidence: 85        │
└─────────────────┘            │ Fact 2                  │
                               │   ...                   │
                               └─────────────────────────┘
```

## Tool Design

### Supervisor Tools
| Tool | Purpose | When Used |
|------|---------|-----------|
| `ConductResearch` | Delegate research task | Gap identified in draft |
| `ResearchComplete` | Signal completion | Quality met, no critiques |
| `refine_draft_report` | Integrate findings | After research returns |
| `think_tool` | Strategic reflection | Before major decisions |

### Researcher Tools
| Tool | Purpose | When Used |
|------|---------|-----------|
| `tavily_search` | Web search | Always (core function) |
| `think_tool` | Reflect on findings | After each search |

### Tool Binding Pattern
```python
# Bind tools to model once
supervisor_model_with_tools = model.bind_tools(supervisor_tools)

# Then invoke with messages
response = await supervisor_model_with_tools.ainvoke(messages)
```

## Command-Based Routing

### Why Commands?
Traditional LangGraph uses `add_conditional_edges` for routing. Commands provide:
1. **Dynamic routing**: Decide destination at runtime
2. **Parallel fan-out**: `goto=["node1", "node2"]`
3. **State updates with routing**: Single return handles both

### Command Pattern
```python
from langgraph.types import Command
from typing import Literal

async def supervisor(state) -> Command[Literal["supervisor_tools"]]:
    # ... logic ...
    return Command(
        goto="supervisor_tools",
        update={
            "supervisor_messages": [response],
            "research_iterations": state.research_iterations + 1,
        }
    )

async def supervisor_tools(state) -> Command[Literal["red_team", "context_pruner", "__end__"]]:
    # Parallel execution
    return Command(
        goto=["red_team", "context_pruner"],  # Both run concurrently
        update={...}
    )
```

## Quality Control Mechanisms

### 1. Judge Model Evaluation
```python
class QualityEvaluation(BaseModel):
    comprehensiveness_score: int  # 1-10
    accuracy_score: int           # 1-10
    specific_critique: str
    missing_aspects: list[str]
```

**Scoring Interpretation**:
- 8-10: Publication ready
- 7: Acceptable (threshold default)
- 5-6: Significant issues
- 1-4: Major revision needed

### 2. Red Team Adversary
The red team is instructed to be "NOT helpful" - to find flaws:

```python
prompt = """
Your goal is NOT to be helpful. Your goal is to find:
1. Claims that lack citations
2. Logical leaps
3. Significant bias

If solid, output exactly "PASS".
Otherwise, output specific critique.
"""
```

### 3. Quality Gate Logic
Multiple mechanisms prevent premature completion:

```python
# In supervisor_tools
if exceeded_iterations or no_tool_calls or research_complete:
    # Check for blockers
    if (unresolved_critiques or quality_issues) and not exceeded_iterations:
        return Command(
            goto="supervisor",
            update={
                "supervisor_messages": [
                    SystemMessage(content="FORCED CYCLE: ...")
                ]
            }
        )
```

## Prompt Engineering

### System Prompt Structure
Each agent role has a specialized system prompt:

1. **Supervisor**: Diffusion algorithm instructions, available tools, strategy by iteration
2. **Researcher**: Task focus, search guidelines, compression instructions
3. **Red Team**: Adversarial mindset, specific flaw categories
4. **Judge**: Scoring criteria, harshness guidance
5. **Writer**: Synthesis instructions, citation format

### Dynamic Context Injection
The supervisor prompt is augmented with runtime state:

```python
# Inject unaddressed critiques
if unaddressed:
    messages.append(SystemMessage(
        content=f"CRITICAL INTERVENTION REQUIRED.\n{critique_text}"
    ))

# Inject quality warnings
if state.needs_quality_repair:
    messages.append(SystemMessage(
        content="PREVIOUS DRAFT QUALITY WAS LOW..."
    ))
```

## Error Handling

### Graceful Degradation
```python
# Evaluation failure
try:
    result = structured_llm.invoke([...])
except Exception as e:
    return QualityEvaluation(
        comprehensiveness_score=5,
        accuracy_score=5,
        specific_critique=f"Evaluation failed: {e}",
    )

# Context pruning failure
try:
    result = await structured_llm.ainvoke([...])
except Exception as e:
    state_facts = []
    message = f"[SYSTEM] Context Pruning failed: {e}"
```

### Iteration Limits
Hard stop to prevent infinite loops:
```python
max_researcher_iterations = 12

if exceeded_iterations:
    return Command(goto=END, update={...})
```

## Concurrency Model

### Parallel Research
Multiple `ConductResearch` calls execute concurrently:

```python
coros = [
    researcher_agent.ainvoke({
        "research_topic": tc["args"]["research_topic"],
        ...
    })
    for tc in conduct_research_calls
]

results = await asyncio.gather(*coros)
```

### Parallel Fan-Out
Red team and context pruner run simultaneously:

```python
return Command(goto=["red_team", "context_pruner"], update={...})
```

Both nodes execute, and the graph waits for both to complete before continuing.

## Future Enhancements

### Planned Features
1. **Document ingestion**: Process uploaded PDFs/docs as research sources
2. **Citation verification**: Validate that citations support claims
3. **Multi-format output**: Generate Markdown, PDF, DOCX
4. **Custom research sources**: Allow user-specified domains
5. **Intermediate checkpoints**: Save/resume long research sessions

### Architecture Extensions
1. **Additional critique agents**: Fact-checker, bias detector, readability scorer
2. **Hierarchical research**: Break complex queries into sub-queries
3. **Source ranking**: Prioritize authoritative sources
4. **Caching layer**: Reuse search results for similar queries

## Lessons Learned

### 1. State Accumulation
Use `Annotated[list[X], add]` for fields that grow across iterations. Without this, later updates overwrite earlier data.

### 2. Circular Import Prevention
Import graphs inside functions when needed:
```python
async def supervisor_tools(state):
    from app.graphs import researcher_agent  # Inside function
```

### 3. Tool Call Handling
Always check for tool calls before accessing:
```python
tool_calls = getattr(message, 'tool_calls', None) or []
```

### 4. Async Consistency
All node functions must be `async def` for proper LangGraph execution:
```python
async def supervisor(state) -> Command[...]:  # ✓
def supervisor(state) -> Command[...]:         # ✗
```

### 5. Command Type Hints
Include all possible destinations in the type hint:
```python
-> Command[Literal["red_team", "context_pruner", "supervisor", "__end__"]]
```
