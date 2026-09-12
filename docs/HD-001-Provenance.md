# Haya Doctor HD-001 provenance

This milestone generalized behavior already demonstrated by the local host projects. No source repository was modified.

| Source repository | Source HEAD | Source path | Treatment | Reason |
|---|---|---|---|---|
| BoardPrep | `6fe06fcd31a3005f493d0bbf2d3555969ada77f3` | `tools/HayaDoctor/tools/Doctor/Registry/CheckRegistry.php` | Adapted and rewritten | Preserved strict directory/load failures, sorted file discovery, and deterministic priority/class ordering in Haya Doctor's registry. |
| BoardPrep | `6fe06fcd31a3005f493d0bbf2d3555969ada77f3` | `tools/Doctor/Project/Shared/Checks/` | Excluded | Static contract checks are reusable only as host-supplied checks because they depend on BoardPrep snapshots and source layout. |
| PlaylistPilot | `074d7134cdffc2f9efa6f118ab072f8f91a19a35` | `tools/Doctor/Host/PlaylistPilotDoctor.php` | Generalized | Retained the host pattern of supplying project checks and explicit roots without moving PlaylistPilot provider, playlist, or UI logic into the shared core. |
| PlaylistPilot | `074d7134cdffc2f9efa6f118ab072f8f91a19a35` | `tools/HayaDoctor/tools/Doctor/Checks/` | Excluded | Python and playlist-adjacent checks remain host concerns; Haya Doctor only adds a generic PHP runtime check. |

No automated repair, provider integration, content validation, simulation, or project schema logic was copied.
