# 📁 项目结构

```
V1.0.0/
├── admin/      # 后台管理相关PHP文件
├── app/        # 核心功能与工具类
├── config/     # 配置文件
├── install/    # 安装引导
├── public/     # 前端静态资源与上传目录
├── version.json# 版本信息
└── index.php   # 前台入口
```

## 主要目录说明

- **admin**/：后台管理页面，包括图片管理、用户管理、日志、设置等。
- **app**/：核心功能实现，如上传、删除、鉴权、日志、自动更新等。
- **config**/：站点配置和用户信息。
- **public**/：静态资源和图片上传目录。
- **install**/：安装引导脚本。
- **version.json**：当前版本号信息。

---

> 如需详细了解每个文件的作用，请参考源码注释或相关文档。

```
├── config/               # 配置文件
│   ├── config.php
│   └── users.php
├── install/              # 安装引导
│   └── index.php
├── public/               # 前端静态资源与上传目录
│   ├── static/
│   │   └── main.js
│   └── uploads/
├── version.json          # 版本信息
└── index.php             # 前台入口
```


