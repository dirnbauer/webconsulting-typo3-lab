# AI content and image examples

The lab uses the current Netresearch stack and keeps its demo records
reproducible through TYPO3 console commands:

| Package | Installed target |
|---|---:|
| `netresearch/nr-llm` | `0.16.1` |
| `netresearch/nr-vault` | `0.10.1` |
| `netresearch/t3-cowriter` | `3.1.1` |
| `netresearch/nr-mcp-agent` | latest `main` |

`nr-mcp-agent` still declares `nr-llm ^0.12 || ^0.13`, although its current
API usage works with 0.16.1. The root requirement therefore installs 0.16.1
with a temporary `0.13.99` Composer compatibility alias. Keep the alias until
the agent package widens its upstream constraint; the project verification
suite must continue to exercise the combination.

## Configure models and Cowriter examples

Run this after installing extensions or importing a fresh database:

```bash
ddev typo3 sitepackage:configure-ai-examples
ddev typo3 cache:flush
```

The command is idempotent. It:

- creates active model records for `gpt-5.6-terra`, `gpt-5.6-luna`, and
  `gpt-image-2`;
- makes Terra the sole global default model while leaving other explicitly
  configured models available as non-default alternatives;
- routes the Content Assistant, summarizer, translator, SEO, code, default,
  and analytics configurations to Terra;
- reserves Luna for genuinely low-complexity transformations: grammar fixes,
  table/list conversion, and the short Solr query enhancer;
- creates ten active Cowriter tasks with valid instructions and `{{input}}`
  placeholders;
- replaces the broken legacy RTE task in `nr-mcp-agent` with a dedicated
  Terra-backed configuration and backend-assistant task;
- copies only the OpenAI provider's opaque nr-vault identifier into the
  specialized image-service setting; the secret remains encrypted in
  nr-vault; and
- routes the named `image-generation` configuration to `gpt-image-2` and
  gives the OpenAI image service a 300-second timeout.

The default Content Assistant uses Terra because content quality, factual
preservation, translation nuance, and tool use are not low-end work. Luna is
used only where the output is tightly constrained and cheaply verifiable.

OpenAI recommends `medium` reasoning as the balanced starting point and `low`
for latency-sensitive work. `nr-llm` 0.16.1 uses Chat Completions and does not
expose the GPT-5.6 `reasoning.effort` control, so this lab deliberately stores
no inert option: GPT-5.6 uses OpenAI's default `medium` effort. Model-tier
routing still provides the intended Terra/Luna cost boundary.

References: [GPT-5.6 model guidance](https://developers.openai.com/api/docs/guides/latest-model),
[GPT-5.6 Terra](https://developers.openai.com/api/docs/models/gpt-5.6-terra),
and [GPT-5.6 Luna](https://developers.openai.com/api/docs/models/gpt-5.6-luna).

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
