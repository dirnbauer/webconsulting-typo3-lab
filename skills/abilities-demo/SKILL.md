---
name: abilities-demo
description: Demonstrates a skill acting through the abilities registry — reports the installation's sites by calling the system/site-info ability as a governed MCP tool.
allowed-tools: mcp__typo3__ability_system_site-info
metadata:
  category: demo
---

# Abilities demo

This installation's **abilities registry** is available to you as MCP tools,
prefixed `mcp__typo3__ability_*`. They are the only actions you may take —
every call is policy-checked, permission-checked against the acting user, and
recorded in the execution trace. You have no other tools.

## Task

1. Call the `mcp__typo3__ability_system_site-info` ability (it needs no input).
2. From its result, report the TYPO3 version and, for each configured site,
   its identifier, root page id and base URL.
3. Do not attempt anything outside the whitelisted abilities.

This skill exists to prove the link between **skills** (the actor — an LLM
doing a task) and **abilities** (the governed toolset it is allowed to use):
the model can only act through registered abilities.
