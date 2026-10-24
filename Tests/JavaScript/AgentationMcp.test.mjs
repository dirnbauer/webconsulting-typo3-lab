import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { once } from 'node:events';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';

// Exercise the installed third-party CLI: our Zod 4 security override must
// preserve its input validation and MCP-to-HTTP calls.
test('Agentation MCP validates tool calls with patched Zod', async () => {
    const requests = [];
    const http = createServer(async (req, res) => {
        let body = '';
        for await (const chunk of req) body += chunk;
        requests.push({ method: req.method, url: req.url, body: body ? JSON.parse(body) : null });
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(req.url === '/sessions/demo' ? { id: 'demo', annotations: [] } : { id: 'note', status: 'acknowledged' }));
    });
    http.listen(0, '127.0.0.1');
    await once(http, 'listening');
    const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
    const transport = new StdioClientTransport({
        command: process.execPath,
        args: [path.join(root, 'node_modules/agentation-mcp/dist/cli.js'), 'server', '--mcp-only', '--http-url', `http://127.0.0.1:${http.address().port}`],
        stderr: 'pipe',
    });
    const client = new Client({ name: 'lab-security-test', version: '1.0.0' });
    try {
        await client.connect(transport);
        const { tools } = await client.listTools();
        assert.ok(tools.some((tool) => tool.name === 'agentation_get_session'));
        const session = await client.callTool({ name: 'agentation_get_session', arguments: { sessionId: 'demo' } });
        assert.notEqual(session.isError, true);
        assert.ok(session.content.some((entry) => entry.text?.includes('demo')));
        const ack = await client.callTool({ name: 'agentation_acknowledge', arguments: { annotationId: 'note' } });
        assert.notEqual(ack.isError, true);
        const before = requests.length;
        const invalid = await client.callTool({ name: 'agentation_get_session', arguments: { sessionId: 42 } });
        assert.equal(invalid.isError, true);
        assert.equal(requests.length, before, 'invalid input must not reach HTTP');
        assert.ok(requests.some((req) => req.url === '/sessions/demo' && req.method === 'GET'));
        assert.ok(requests.some((req) => req.url === '/annotations/note' && req.method === 'PATCH' && req.body.status === 'acknowledged'));
    } finally {
        await client.close();
        http.closeAllConnections();
        await new Promise((resolve) => http.close(resolve));
    }
});
