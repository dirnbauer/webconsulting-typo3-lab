/**
 * Proves the core bridge seam: Flue's real @flue/runtime can authenticate to
 * typo3-mcp-server over MCP and adapt TYPO3's tools into Flue tool definitions.
 *
 * This needs NO LLM/Anthropic key — it is a pure MCP handshake + tools/list, so
 * it verifies the riskiest, most novel part of the integration on its own.
 *
 *   TYPO3_MCP_TOKEN=<pat>  node probe-mcp.mjs
 *
 * Get a PAT from the "MCP Server" backend module (User settings) — it calls
 * Hn\McpServer\Service\OAuthService::createDirectAccessToken() for a BE user.
 * Inside DDEV use TYPO3_MCP_URL=http://web/mcp; from the host use the
 * https://<project>.ddev.site/mcp URL.
 */
import { connectMcpServer } from '@flue/runtime';

const url = process.env.TYPO3_MCP_URL || 'http://web/mcp';
const token = process.env.TYPO3_MCP_TOKEN;
const transport = process.env.TYPO3_MCP_TRANSPORT || 'streamable-http';

if (!token) {
  console.error('Set TYPO3_MCP_TOKEN to a typo3-mcp-server personal access token.');
  process.exit(2);
}

console.log(`Connecting to TYPO3 MCP at ${url} (transport=${transport}) ...`);
try {
  const conn = await connectMcpServer('typo3', {
    url,
    transport,
    headers: { Authorization: `Bearer ${token}` },
  });
  console.log(`OK — adapted ${conn.tools.length} TYPO3 tools into Flue tool definitions:`);
  for (const t of conn.tools) {
    console.log('  -', t?.name ?? '(unnamed)');
  }
  await conn.close();
  console.log('\nBridge proven: Flue can call TYPO3 over MCP with a PAT. No LLM key was used.');
} catch (err) {
  console.error('\nMCP connection failed:', err?.message ?? err);
  console.error('Checks: TYPO3 up? /mcp reachable from here? PAT valid + not expired? transport correct (try sse)?');
  process.exit(1);
}
