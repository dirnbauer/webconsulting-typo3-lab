import assert from 'node:assert/strict';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { createState, keyPair, PoppyClient, proof, sign } from '../../packages/poppy/Examples/pi-durable/src/poppy-client.mjs';
const origin = process.env.POPPY_TEST_ORIGIN;
const clientId = process.env.POPPY_TEST_CLIENT;
const dir = process.env.POPPY_TEST_DIR;
const publicDir = process.env.POPPY_TEST_PUBLIC;
const stateFile = dir + '/private.json';
if (process.argv[2] === 'prepare') {
  const state = await createState(await keyPair());
  await writeFile(stateFile, JSON.stringify(state), { mode: 0o600 });
  await writeFile(publicDir + '/agent.json', JSON.stringify({ client_id: clientId, client_name: 'Ephemeral TYPO3 protocol test',
    jwks_uri: clientId.replace('agent.json', 'jwks.json'), redirect_uris: [], token_endpoint_auth_method: 'private_key_jwt' }));
  await writeFile(publicDir + '/jwks.json', JSON.stringify({ keys: [{ ...state.identity.public, kid: 'agent-key-1', alg: 'ES256', use: 'sig' }] }));
  process.exit(0);
}
const state = JSON.parse(await readFile(stateFile));
const save = s => writeFile(stateFile, JSON.stringify(s), { mode: 0o600 });
const client = new PoppyClient(origin, clientId, state, save);
let checks = 0;
function passed(name) { checks++; console.log('PASS ' + name); }
const discovery = await client.discover();
assert.equal(discovery.protocol_version, '0.1'); assert.deepEqual(discovery.topics, ['poppy', 'pi-durable']); passed('reciprocal discovery and OpenAPI');
const publicApi = await fetch(origin + '/poppy/knowledge?topic=poppy'); assert.equal(publicApi.status, 401); passed('anonymous API access rejected');
await client.session(); assert.equal(state.token.signed_in, false); assert.equal(state.token.scope, ''); passed('verified DPoP guest session');
const answer = await client.knowledge('poppy'); assert.match(answer.answer, /signed-out profile/); assert.equal(answer.source, origin + '/features/poppy/'); passed('answer read from native TYPO3 content');
const pi = await client.knowledge('pi-durable'); assert.match(pi.answer, /Pi Durable/); passed('Pi Durable topic');
const session = state.token.session_id;
const restored = JSON.parse(await readFile(stateFile));
const resumed = new PoppyClient(origin, clientId, restored, save);
await resumed.session(true); assert.equal(restored.token.session_id, session);
assert.notEqual(restored.token.access_token, state.token.access_token); await resumed.knowledge('poppy'); passed('private state restored and same session renewed');
const url = origin + '/poppy/knowledge?topic=poppy';
const jwt = await proof(restored.dpop, 'GET', url, restored.token.access_token);
const headers = { Authorization: 'DPoP ' + restored.token.access_token, DPoP: jwt };
assert.equal((await fetch(url, { headers })).status, 200);
const replay = await fetch(url, { headers }); const replayBody = await replay.json(); assert.equal(replayBody.error, 'invalid_dpop_proof'); assert.equal(replay.status, 401); passed('DPoP replay rejected by persistent store');
const badKey = await keyPair();
const wrong = await fetch(url, { headers: { ...headers, DPoP: await proof(badKey, 'GET', url, restored.token.access_token) } });
assert.equal((await wrong.json()).error, 'invalid_dpop_proof'); passed('copied token without bound private key rejected');
const bearer = await fetch(url, { headers: { Authorization: 'Bearer ' + restored.token.access_token } }); assert.equal(bearer.status, 401); passed('Bearer downgrade rejected');
const queryToken = await fetch(url + '&access_token=' + restored.token.access_token, { headers: { ...headers, DPoP: await proof(restored.dpop, 'GET', url, restored.token.access_token) } });
assert.equal(queryToken.status, 401); passed('tokens in URL rejected');
const invalidTopic = await fetch(origin + '/poppy/knowledge?topic=other', { headers: { Authorization: 'DPoP ' + restored.token.access_token, DPoP: await proof(restored.dpop, 'GET', url, restored.token.access_token) } });
assert.equal(invalidTopic.status, 404); passed('unlisted content rejected');
const endpoint = origin + '/poppy/token';
const claims = sub => ({ iss: clientId, sub, aud: endpoint, iat: Math.floor(Date.now()/1000), exp: Math.floor(Date.now()/1000)+60, jti: crypto.randomUUID() });
const form = async extra => new URLSearchParams({ grant_type: 'urn:ietf:params:oauth:grant-type:jwt-bearer', client_id: clientId,
  client_assertion_type: 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
  client_assertion: await sign(claims(clientId), state.identity), assertion: await sign(claims(state.user), state.identity), ...extra });
const tokenRequest = async body => fetch(endpoint, { method:'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', DPoP: await proof(state.dpop,'POST',endpoint) }, body });
const body = await form({}); assert.equal((await tokenRequest(body)).status,200);
const duplicate = await tokenRequest(body); assert.equal((await duplicate.json()).error,'invalid_client'); passed('client assertion replay rejected');
const elevated = await tokenRequest(await form({scope:'poppy:write'})); assert.equal((await elevated.json()).error, 'invalid_scope'); passed('scope escalation rejected');
const hijack = await tokenRequest(await form({session_id:session, assertion: await sign(claims('usr_other_opaque'), state.identity)}));
assert.equal((await hijack.json()).error, 'invalid_session'); passed('another user cannot renew the session');
// Exercise native content changes and publication rules through DataHandler, and restore in finally.
const uid = Number(execFileSync('ddev', ['mysql','-N','-e', "SELECT uid FROM tt_content WHERE pid=(SELECT uid FROM pages WHERE slug='/features/poppy' AND deleted=0 AND sys_language_uid=0 LIMIT 1) AND header='What TYPO3 Lab says' AND deleted=0 AND sys_language_uid=0 LIMIT 1"], {encoding:'utf8'}).trim());
assert.ok(uid > 0);
const nativeHtml = execFileSync('ddev',['mysql','--batch','--raw','--skip-column-names','-e',`SELECT bodytext FROM tt_content WHERE uid=${uid}`],{encoding:'utf8'}).trimEnd();
const payloadPath = 'packages/site_package/Resources/Private/Data/Content/poppy/temporary-test.payload.json';
async function apply(set) {
  await writeFile(payloadPath, JSON.stringify({version:1,site:'desiderio',root:505,language:0,records:[{table:'tt_content',uid,set}]}));
  execFileSync('ddev',['exec','vendor/bin/typo3','sitepackage:content:apply','EXT:site_package/Resources/Private/Data/Content/poppy/temporary-test.payload.json'],{stdio:'pipe'});
}
try {
  await apply({hidden:1}); await assert.rejects(()=>resumed.knowledge('poppy'), /not_found/); passed('hidden native content not exposed');
  await apply({hidden:0, bodytext:'<p>Temporary native answer for the integration test.</p>'});
  assert.equal((await resumed.knowledge('poppy')).answer,'Temporary native answer for the integration test.'); passed('native editorial update changes API answer');
} finally {
  await apply({hidden:0,bodytext:nativeHtml});
  const {unlink} = await import('node:fs/promises'); await unlink(payloadPath);
}
assert.match((await resumed.knowledge('poppy')).answer,/signed-out profile/); passed('original CMS content restored');
console.log(`${checks} authenticated TYPO3 integration checks passed.`);
