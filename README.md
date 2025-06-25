# 🎨 LeisurePic 图床系统

[![Release](https://img.shields.io/github/v/release/leisureea1/Leisurepic?color=%2337c6ff)](https://github.com/leisureea1/Leisurepic/releases)
[![Stars](https://img.shields.io/github/stars/leisureea1/Leisurepic?style=social)](https://github.com/leisureea1/Leisurepic/stargazers)
[![License](https://img.shields.io/github/license/leisureea1/Leisurepic)](LICENSE)
[![Hits](https://hits.sh/github.com/leisureea1/leisurepic.svg?style=flat-square)](https://hits.sh/github.com/leisureea1/leisurepic/)

> 🖼️ 一个轻量、纯 PHP、带在线管理面板的图床系统，支持多种上传方式、图像处理、权限控制和在线更新，适合自用或团队部署。

---

## ✨ 效果展示

<p align="center">
  <img src="https://as.leisureea.com/public/uploads/2025/06/25/9cz89e.webp" width="700" alt="首页界面">
</p >

- ✅ 图像压缩 / 水印 / 格式转换  
- ✅ EXIF 自动旋转  
- ✅ 在线用户管理 + 日志记录  
- ✅ IP拉黑  
- ✅ 无数据库纯文件存储  
- ✅ 图片鉴黄
- ✅ Bark推送
- ✅ 微信推送(考虑支持)
- ✅ 在线更新

---

## 💡 创作初衷

开发过程中，市面上图床要么部署复杂、要么广告泛滥、要么依赖数据库或外部服务，使用体验始终不够爽。

我便萌生一个想法：  
**能不能做一个纯 PHP 实现、可配置、部署容易的图床系统？**

于是 LeisurePic 就这样诞生了：

- 轻量 → 纯文件存储 + 无框架依赖  
- 安全 → IP 限制 / Token 上传 / 用户登录  
- 实用 → 支持压缩、水印、EXIF、在线更新  
- 易部署 → 使用宝塔面板

---

## 🚀 快速部署（详细见文档）

建议前往文档查看完整部署方式（含 宝塔 / 手动配置等）：

📘 [点击查看完整文档](https://leisureea.github.io/leisurepic/) 暂时没写。。。通过宝塔部署即可所有目录755 权限

---

## 🌟 系统亮点

| 功能模块       | 说明                                                                 |
|----------------|----------------------------------------------------------------------|
| 📂 图像上传     | 支持拖拽、多选、本地选择、复制粘贴等上传方式                        |
| 🖼 图像处理     | 使用 GD 库进行压缩、水印、EXIF 修正、格式转换                       |
| 🔐 权限控制     | 支持 IP 限制、登录验证、防滥用                                    |
| 🔄 在线更新     | 自带版本检查与增量自动更新机制，支持 SHA256 校验与配置合并          |
| 📊 数据记录     | 上传日志、用户操作记录、版本更新记录                               |
| ⚙️ 配置集中管理 | 所有配置集中于 `config/config.php`，支持在线合并新配置             |

---

## ⚙️ 配置项说明（部分）

配置文件位于 `config/config.php`，以下为部分字段说明：

| 键名 | 说明 |
|------|------|
| `site_name` | 网站名称显示 |
| `upload_dir` | 上传图片存储目录（相对路径） |
| `max_upload_size` | 单张图最大体积（MB） |
| `enable_watermark` | 是否开启水印功能 |
| `site_domain` | 网站域名 |
| `max_upload_concurrency` | 最大并发数 |
| `default_format` | 默认格式 |
| `allowed_types` | 允许上传格式 |
| `upload_limit_per_day` | 每日上传限制 |

> ✏️ 所有配置支持在线更新后自动合并，无需手动干预！

---

## 🧩 文件结构（简要）

```text
📁 根目录
├── index.php              # 系统入口
├── config/
│   ├── config.php         # 主配置文件
│   ├── users.php          # 用户配置
├── app/
│   ├── core.php           # 核心功能函数
│   ├── merge_config.php   # 配置合并脚本
├── public/uploads/        # 图片存储目录
├── install/
├   ├──index.php           # 安装页面
├── version.json           # 当前版本号
```

---

## 🔗 友情链接

- 🏠 我的博客：[blog.leisureea.com](https://blog.leisureea.com/)
- 📖 项目文档：[leisureea.github.io/leisurepic](https://leisureea.github.io/leisurepic/)

---

## ❤️ 打赏支持（感谢您的鼓励）

如果您觉得这个项目对您有帮助，欢迎打赏支持！

<table>
<tr>
<td align="center">微信</td>
<td align="center">支付宝</td>
</tr>
<tr>
<td><img src="https://as.leisureea.com/public/uploads/2025/06/26/TfSp7M.png" width="180"/></td>
<td><img src="https://as.leisureea.com/public/uploads/2025/06/26/xGaPhY.png" width="180"/></td>
</tr>
</table>

---

## 📝 开源协议

本项目遵循 MIT License 开源协议，您可以自由地使用、修改、再分发。请勿将本项目用于违法用途。  
如有引用或转载，烦请注明出处。

> MIT License © [leisureea](https://github.com/leisureea)

---

## 📮 联系我

如有问题、建议、合作意向，欢迎提交 [Issue](https://github.com/leisureea/leisurepic/issues)，或者通过博客与我联系！

---

_感谢您看到这里，祝使用愉快！_
