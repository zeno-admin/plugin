# Zeno Plugin

Zeno Admin 的 Composer 插件运行时。

它负责发现业务插件，注册 Laravel 路由、迁移和语言包，导入初始菜单，并提供插件安装、升级、启用、停用与卸载生命周期。插件可以是纯 Laravel 模块，也可以携带独立构建的 React 前端。

## 要求

- PHP 8.3+
- Laravel 13
- Inertia Laravel 3
- Composer 2.2+

## 安装

```bash
composer require zeno/plugin zeno/plugin-installer
php artisan vendor:publish --tag=zeno-plugin-config
php artisan migrate
```

`zeno/plugin` 通过 Laravel Package Discovery 自动注册。`zeno/plugin-installer` 将业务插件安装到项目的 `plugins` 目录。

核心通过 Composer 的 `InstalledVersions::getInstalledPackagesByType('zeno-plugin')` 发现插件。插件 key 自动取 Composer package 的 slug，例如 `zeno/bookmarks` 对应 `bookmarks`，安装目录为 `plugins/bookmarks`。不同 package 使用相同 slug 时会中断安装或启动并指出冲突。

## 创建插件

使用固定 Stub 创建完整插件模板：

```bash
php artisan zeno:plugin:make acme/tickets
```

省略 package 时，命令通过 Laravel Prompts 询问 Composer package：

```bash
php artisan zeno:plugin:make
```

命令默认生成到 `packages/tickets`，已存在的目标目录不会被覆盖。模板包含 Composer manifest、菜单、后台路由、Controller、五阶段 Hook、中英文语言文件以及完整的 React/Vite 前端：

```text
packages/tickets/
├── composer.json
├── plugin.php
├── README.md
├── LICENSE
├── routes/admin.php
├── src/
│   ├── TicketsPluginHook.php
│   └── Http/Controllers/TicketsController.php
├── lang/
│   ├── en/admin.php
│   └── zh_CN/admin.php
└── frontend/
    ├── package.json
    ├── tsconfig.json
    ├── vite.config.ts
    ├── eslint.config.js
    └── src/
        ├── plugin.ts
        ├── plugin.css
        ├── support.ts
        └── pages/index.tsx
```

先构建插件前端：

```bash
cd packages/tickets/frontend
npm install
npm run lint
npm run types:check
npm run build
```

然后在宿主项目 `composer.json` 中添加 `packages/tickets` Path Repository，并执行：

```bash
composer require acme/tickets:@dev
php artisan migrate
npm run build
```

Composer 会将插件安装到 `plugins/tickets`。成功的 `migrate` 会导入菜单、执行 `install` 和 `enable` Hook；宿主前端构建会加载插件已发布的前端入口。插件模板本身不修改宿主 `composer.json`，也不执行 Composer、npm 或数据库命令。

生成后的插件 README 包含完整的本地加载步骤。

业务插件是一个 Composer package：

```json
{
    "name": "acme/incident-desk",
    "type": "zeno-plugin",
    "require": {
        "php": "^8.3",
        "zeno/plugin": "^1.0",
        "zeno/plugin-installer": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Acme\\IncidentDesk\\": "src/"
        }
    }
}
```

插件 key：

- 自动取 Composer package `/` 后的 slug。
- 只包含小写字母、数字和连字符。
- 在应用中保持唯一；不同 vendor 不能使用相同 slug。

例如 `acme/incident-desk` 使用 `incident-desk`。

## 目录约定

```text
plugin.php
config/plugin.php              # 可选
routes/admin.php               # 可选，登录 + 宿主授权
routes/authenticated.php       # 可选，仅要求登录
database/migrations/           # 可选
lang/{locale}/admin.php        # 可选
frontend/dist/plugin.js        # 存在 frontend 时必需
frontend/dist/plugin.css       # 可选
```

纯后端插件可以省略整个 `frontend` 目录。

`config/plugin.php` 可选，内容会加载到 `config('plugins.{key}')`。宿主发布的配置为插件路由提供 `web`、`auth:admin`、`plugin.enabled:{key}` 和授权 middleware；`routes/authenticated.php` 只要求管理员登录，`routes/admin.php` 还要求宿主配置的授权检查。核心后台与插件路由统一使用 `permission.check` 中间件别名，宿主将该别名映射到自己的权限检查实现。

## Manifest

根目录的 `plugin.php` 声明插件名称和初始菜单：

```php
<?php

use Zeno\Plugin\Definitions\MenuDefinition;

return [
    'name' => 'Incident Desk',
    'hook' => \Acme\IncidentDesk\IncidentDeskHook::class,
    'menus' => [
        new MenuDefinition(
            titleKey: 'plugins.incident-desk.nav.group',
            icon: 'shield_alert',
            children: [
                new MenuDefinition(
                    titleKey: 'plugins.incident-desk.nav.index',
                    routeName: 'incident-desk.index',
                    icon: 'activity',
                ),
            ],
        ),
    ],
];
```

`PluginHook` 在手工创建插件时可选，由 `zeno:plugin:make` 创建的模板会默认生成并注册：

```php
<?php

namespace Acme\IncidentDesk;

use Zeno\Plugin\PluginHook;

final class IncidentDeskHook extends PluginHook
{
    public function install(): void {}

    public function enable(): void {}

    public function disable(): void {}

    public function upgrade(string $fromVersion, string $toVersion): void {}

    public function uninstall(): void {}
}
```

Hook 通过 Laravel 容器创建，可以使用构造函数依赖注入。方法执行失败时抛出异常；所有方法应保持幂等，以支持安全重试。

菜单会在 `sync` 时重新导入后台菜单表，之后可以继续在菜单管理中编辑。菜单数据属于宿主业务数据，插件停用或卸载不会删除。

## 路由

插件路由使用 Controller action 和命名路由：

```php
<?php

use Acme\IncidentDesk\Http\Controllers\IncidentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IncidentController::class, 'index'])->name('index');
```

`routes/admin.php` 接入宿主配置的授权中间件。`routes/authenticated.php` 只要求管理员登录。

以上示例最终注册为：

```text
GET /admin/incident-desk
name: admin.incident-desk.index
```

插件路由都会经过 `plugin.enabled:{key}` gate。记录不存在或状态为 Disabled、Uninstalled 时返回 404；路由必须使用 Controller action、命名，并位于插件自己的命名空间。

## 迁移与语言

插件迁移通过 Laravel `loadMigrationsFrom()` 注册，统一执行：

```bash
php artisan migrate
```

语言文件使用 Laravel package namespace，namespace 与插件 key 相同：

```php
__('incident-desk::admin.plugins.incident-desk.title');
```

Inertia shared translations 合并宿主语言、所有 enabled 插件的 nav，以及当前插件页面的完整语言字典。

## 前端

带前端的插件发布已构建入口：

```text
frontend/dist/plugin.js
frontend/dist/plugin.css
frontend/dist/chunks/**
```

入口契约：

```ts
import './plugin.css';

export default {
    contract: 1,
    key: 'incident-desk',
    pages: {
        index: () => import('./pages/index'),
    },
};
```

插件负责构建自己的前端产物，Zeno Admin 通过以下 glob 加载入口和样式并挂载后台布局：

```ts
import.meta.glob('../../plugins/*/frontend/dist/plugin.js');
import.meta.glob('../../plugins/*/frontend/dist/plugin.css', { eager: true });
```

插件 Vite 应将 React、React DOM、Inertia React 及宿主 `@/components/ui/*` 标记为 external，由宿主统一解析。

插件 Tailwind utilities 放在低优先级 `zeno-plugins` layer，宿主保持更高优先级：

```css
@layer theme, base, zeno-plugins, components, utilities;
```

带前端的插件路由默认使用 CSR，核心会对 `admin/{key}` 及其子路径调用 `Inertia::withoutSsr()`。

## 验证边界

核心验证 Composer package type、由 package slug 得到的插件 key 及其唯一性、manifest 数组与名称、菜单类型和命名空间、前端入口、路由命名与 Controller action。宿主公开 `PluginException` 及其 `InvalidPluginException`、`PluginNotFoundException`、`PluginNotSyncedException`、`PluginHookException`、`PluginOperationException` 子类；数据库、路由解析和 PHP 文件错误保留 Laravel/PHP 原始异常。

## 生命周期

通过 Composer 安装、升级或移除插件时，`zeno/plugin-installer` 会把当前 package operation 转交给核心：

```text
composer require  -> 写入 plugins/.zeno-pending.json
composer update   -> 写入 plugins/.zeno-pending.json
php artisan migrate -> 成功且非 --pretend 时消费 pending，执行 install/upgrade、菜单导入和 enable
composer remove   -> disable -> uninstall -> Composer 删除代码
```

Composer install/update 阶段只写入 `plugins/.zeno-pending.json`，不执行数据库或生命周期 Hook。成功的非 `--pretend` `php artisan migrate` 完成后消费 pending；失败、`--pretend` 和测试运行不会消费。只处理 Composer 当前 operation 指定的插件。

```bash
php artisan zeno:plugin:list
php artisan zeno:plugin:sync incident-desk
php artisan zeno:plugin:enable incident-desk
php artisan zeno:plugin:disable incident-desk
```

正常安装：

```bash
composer require acme/incident-desk
php artisan migrate
```

Composer 和 migrate 会自动完成 install 与 enable Hook。`sync` 只重新导入菜单，保留插件版本、reference 和当前状态；`enable` 与 `disable` 用于手动切换状态。卸载完成后状态记录保留为 Uninstalled，重新安装时再次执行安装 Hook。菜单和业务数据始终保留。

## License

[MIT](LICENSE)
