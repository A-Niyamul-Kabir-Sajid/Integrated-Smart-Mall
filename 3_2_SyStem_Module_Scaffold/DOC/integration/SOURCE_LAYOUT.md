# Source layout

`DOC/modules/<number_name>/GUIDE.md` describes the same module as `modules/<number_name>/`.

Each module contains named functional subcomponents. Each subcomponent reserves `frontend/`, `backend/`, and `tests/`. Empty directories deliberately contain no application code. Use a layer only where needed; routing can live in browser code while purchase verification must be authoritative on the server.

Begin as one modular application with one shared backend, database and application-level dependency setup unless actual requirements justify more. “Package” here means functional module, not an npm/Composer package or independent deployment.

Framework setup is pending. If Laravel is chosen, either explicitly configure PHP autoload, module registration, frontend imports and test discovery for this layout, or move the backend/frontend layers to conventional app/Modules and resources/js/modules paths while retaining these 12 module names. Update this mapping rather than retaining duplicate active code copies. Do not assume framework discovery works automatically.

M12 contains shared technical services. Domain contracts can be implemented under the owning module and exposed through a documented public interface; do not put all business code into M12.
