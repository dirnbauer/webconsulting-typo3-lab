# AI content and image examples

The lab uses the current Netresearch stack and keeps its demo records
reproducible through TYPO3 console commands:

| Package | Installed target |
|---|---:|
| `netresearch/nr-llm` | `0.35.0` |
| `netresearch/nr-vault` | `0.16.0` |
| `netresearch/t3-cowriter` | `3.6.8` |

The backend chat is `webconsulting/typo3-ai-assistant`; it runs the
installation's own MCP tools in-process through nr-llm's agent runtime. See
[AI Assistant](ai-assistant.md).

## Configure models and Cowriter examples

Run this after installing extensions or importing a fresh database:

```bash
ddev typo3 sitepackage:configure-ai-examples
ddev typo3 cache:flush
```

The command is idempotent. It:

- creates active model records for `gpt-5.6-terra`, `gpt-5.6-luna`,
  `gpt-5-mini`, and `gpt-image-2`;
- makes Terra the sole global default model while leaving other explicitly
  configured models available as non-default alternatives;
- routes the Content Assistant, summarizer, translator, SEO, code, default,
  and analytics configurations to Terra;
- reserves Luna for genuinely low-complexity transformations: grammar fixes,
  table/list conversion, and the short Solr query enhancer;
- creates ten active Cowriter tasks with valid instructions and `{{input}}`
  placeholders;
- replaces the broken legacy RTE task in `nr-mcp-agent` with a dedicated
  `gpt-5-mini` configuration and backend-assistant task;
- copies only the OpenAI provider's opaque nr-vault identifier into the
  specialized image-service setting; the secret remains encrypted in
  nr-vault; and
- routes the named `image-generation` configuration to `gpt-image-2` and
  gives the OpenAI image service a 300-second timeout.

The default Content Assistant uses Terra because content quality, factual
preservation, and translation nuance are not low-end work. Luna is used only
where the output is tightly constrained and cheaply verifiable.

The lab does not patch nr-llm. Stock nr-llm 0.25 sends OpenAI tool calls through
`/v1/chat/completions`, and current GPT-5.6 Chat Completions support function
tools directly. The former lab patch that switched selected configurations to
the Responses API is therefore no longer required.

nr-llm 0.25 does not translate its generic `think` option to OpenAI's
`reasoning_effort` parameter on this path. The old configuration JSON
`{"api_mode":"responses","reasoning_effort":"none"}` was consequently both
provider-specific and ineffective in stock 0.25; the setup command now clears
that stale `options` value from all lab-owned configurations. The dedicated
`TYPO3 Backend Assistant` remains on `gpt-5-mini`, while editorial configurations
use Terra or Luna according to their quality/cost tier.

References: [GPT-5.6 model guidance](https://developers.openai.com/api/docs/guides/latest-model),
[GPT-5.6 Terra](https://developers.openai.com/api/docs/models/gpt-5.6-terra),
and [function tools](https://developers.openai.com/api/docs/guides/tools).

## TYPO3 settings

### nr-llm

Providers, models, configurations, and tasks are database-backed records in
**Administration → LLM**. `nr-llm` does not read `plugin.tx_nrllm` TypoScript,
so adding provider or model settings to a TypoScript template has no effect.

The lab command maintains these extension-level settings in
`config/system/settings.php`:

```php
'nr_llm' => [
    'circuitBreaker' => [
        'enabled' => '1',
        'failureThreshold' => '5',
        'cooldownSeconds' => '30',
    ],
    'image' => [
        'dalle' => [
            'defaultSize' => '1024x1024',
            'timeout' => '300',
        ],
    ],
    'privacy' => [
        'level' => 'metadata',
        'retentionDays' => '30',
    ],
    'providers' => [
        'openai' => [
            // Opaque nr-vault identifier, never the API key itself.
            'apiKeyIdentifier' => 'openai_api_key',
        ],
    ],
    'telemetry' => ['enabled' => '1'],
    'tools' => ['dataClassEnforcement' => 'enforce'],
],
```

The OpenAI provider record additionally uses a 120-second API timeout and the
`external, global` trust zone. Upgrade wizards
`nrLlm_providerApiTimeout120` and `nrLlm_stampProviderTrustZone` normalize those
values after updating from an older nr-llm release.

### Cowriter

Cowriter 3.x no longer has its own API-key or model settings. It resolves the
active/default nr-llm configuration and displays its diagnostic result in
**Administration → Cowriter Status**.

Generic TYPO3 rich-text fields use the Cowriter preset through:

```typoscript
RTE.default.preset = cowriter
```

Desiderio Content Blocks explicitly select `richtextConfiguration: desiderio`,
so the default Page TSconfig does not reach them. The site package therefore
re-registers that preset with
[`DesiderioCowriter.yaml`](../packages/site_package/Configuration/RTE/DesiderioCowriter.yaml):

```yaml
imports:
  - { resource: 'EXT:desiderio/Configuration/RTE/Desiderio.yaml' }

editor:
  config:
    importModules:
      - { module: '@netresearch/t3_cowriter/cowriter', exports: ['Cowriter'] }
    toolbar:
      items:
        - cowriter
        - cowriterVision
        - cowriterTranslate
        - cowriterTemplates
```

This preserves Desiderio's headings, semantic inline styles, language markup,
and abbreviation plugin while adding all four Cowriter controls. The existing
frontend middleware remains necessary for TYPO3's Visual Editor `editMode=1`
iframe: it explicitly queues the Cowriter JavaScript modules so TYPO3 emits
their import-map entries. It does not run on normal frontend requests.

## Create the two frontend manuals

The checked-in screenshots and manual content are reproducible:

```bash
ddev typo3 sitepackage:seed-ai-manuals
ddev typo3 cache:flush
```

The idempotent command creates or refreshes exactly one page per extension:

- `/features/nr-llm-manual`
- `/features/cowriter-manual`

It imports the backend screenshots into `fileadmin/ai-manual/` through FAL and
attaches them to normal TYPO3 content elements. Re-run it after replacing a
screenshot or importing a fresh database.

## Generate images with GPT Image 2

The requested model spelling is `gpt-image-2` (not `image-gtp-2`). The example
command uses `nr-llm`'s OpenAI Images API service, resolves the
`image-generation` configuration, prepends its maintained image prompt, and
saves the returned base64 image under `var/generated-images/` by default.
The installed service does not consume its legacy extension-level
`defaultModel` key, so the example deliberately resolves the database
configuration and passes `gpt-image-2` explicitly.

Square website image:

```bash
ddev typo3 sitepackage:llm:generate-image \
  'Editorial photograph of a TYPO3 team planning a multilingual website, warm natural light, generous negative space'
```

Landscape hero image:

```bash
ddev typo3 sitepackage:llm:generate-image \
  'Abstract European digital-sovereignty infrastructure, calm blue and amber palette, no text' \
  --size=1536x1024 \
  --output=var/generated-images/sovereign-hero.png
```

Explicit model override:

```bash
ddev typo3 sitepackage:llm:generate-image \
  'Accessible editorial illustration of an author collaborating with an AI assistant, no text' \
  --model=gpt-image-2 \
  --size=1024x1536
```

The command does not run during setup or tests because image generation incurs
API cost. OpenAI may require organization verification before GPT Image models
can be used. The Image API is appropriate here because it guarantees the
specific `gpt-image-2` model; the Responses API image tool selects its own image
model.

See the official [GPT Image 2 model page](https://developers.openai.com/api/docs/models/gpt-image-2)
and [image generation guide](https://developers.openai.com/api/docs/guides/image-generation).

### Equivalent PHP service call

```php
use Netresearch\NrLlm\Specialized\Image\DallEImageService;
use Netresearch\NrLlm\Specialized\Option\ImageGenerationOptions;

$model = $imageService->resolveModelForConfiguration('image-generation', 'gpt-image-2');
$result = $imageService->generate(
    'A clean editorial illustration for a TYPO3 article, no text',
    new ImageGenerationOptions(
        model: $model,
        size: '1024x1024',
        quality: null,
        style: null,
        format: null,
        configuration: 'image-generation',
    ),
);

$result->saveToFile('/absolute/path/to/image.png');
```

`quality`, `style`, and `format` are deliberately `null` in this adapter-level
example. GPT Image 2 returns base64 data and the installed `nr-llm` service
currently delegates those newer output controls to the API defaults.
