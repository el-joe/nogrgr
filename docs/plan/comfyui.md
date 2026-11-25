# ComfyUI (local image generation) — approved by client 2026-11-24

- URL: http://127.0.0.1:8188 (ComfyUI 0.46, RTX 5060 8 GB). Use only when an image asset is needed.
- Text-to-image model: **Z-Image Turbo** — `UNETLoader` `z_image_turbo_nvfp4.safetensors` + `CLIPLoader` `qwen_3_4b_fp8_mixed.safetensors` (type `lumina2`) + `VAELoader` `ae.safetensors`; ~8 steps, cfg 1.0, sampler `res_multistep`/`euler`, 1024×1024 or banner sizes (multiples of 64). Video: `wan2.2_ti2v_5B` (not needed now).
- API: `POST /prompt` with an API-format workflow JSON → poll `GET /history/{prompt_id}` → download `GET /view?filename=…&type=output`.
- Use for: banners/hero art, empty-state & onboarding illustrations, theme preview imagery, demo product photos for designs, app splash art. Not for logos of real brands or copyrighted characters.
- Save outputs to `backend/resources/images/<area>/` (optimize to AVIF/WebP) or upload into Figma on our own pages only.
