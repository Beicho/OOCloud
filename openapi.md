接口域名为： https://open-api.123pan.com 
client_secret 请注意安全保存，不要放在前端代码里
所有请求接口均需要带上请求头 platform 值为:open_platform
为确保所有用户的使用体验，123云盘开放平台会对下面 API 进行限流：
API
限制QPS（同一个client_id，每秒最大请求次数）
api/v1/user/info
1
api/v1/file/move
1
api/v1/file/delete
1
api/v1/file/list
4
api/v2/file/list
3
upload/v1/file/mkdir
2
upload/v1/file/create
2
api/v1/access_token
1
api/v1/share/list  
10
api/v1/share/list/info  
10
api/v1/transcode/folder/info  
20
api/v1/transcode/upload/from_cloud_disk  
1
api/v1/transcode/delete  
10
api/v1/transcode/video/resolutions   
1
api/v1/transcode/video
3
api/v1/transcode/video/record  
20
api/v1/transcode/video/result 
20
api/v1/transcode/file/download  
10
api/v1/transcode/m3u8_ts/download  
20
api/v1/transcode/file/download/all  
1

获取access_token
API： POST 域名 +/api/v1/access_token
注：此接口有访问频率限制。请获取到access_token后本地保存使用，并在access_token过期前及时重新获取。access_token有效期根据返回的expiredAt字段判断。
Header 参数
名称
类型
是否必填
说明
 Platform
string
是
 open_platform 
Body 参数
名称
类型
是否必填
说明
clientID
string
必填

clientSecret
string
必填

返回数据
名称
类型
是否必填
说明
accessToken
string
必填
访问凭证
expiredAt
string
必填
access_token过期时间

💡上传流程说明
分片上传
1
创建文件
a
调用创建文件接口，接口返回的reuse为true时，表示秒传成功，上传结束。
b
非秒传情况将会返回预上传IDpreuploadID与分片大小sliceSize，请将文件根据分片大小切分。
c
非秒传情况下返回servers为后续上传文件的对应域名（重要），多个任选其一。
2
上传分片
a
该步骤准备工作，按照sliceSize将文件切分，并计算每个分片的MD5。
b
调用上传分片接口，传入对应参数，注意此步骤 Content-Type: multipart/form-data。
3
上传完毕
a
调用上传完毕接口，若接口返回的completed为 ture 且fileID不为0时，上传完成。
b
若接口返回的completed为 false 时，则需间隔1秒继续轮询此接口，获取上传最终结果。


创建文件
API： POST   域名 + /upload/v2/file/create
说明：
●
文件名要小于256个字符且不能包含以下任何字符："\/:*?|><
●
文件名不能全部是空格
●
开发者上传单文件大小限制10GB
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
Body 参数
名称
类型
是否必填
说明
parentFileID
number
必填
父目录id，上传到根目录时填写 0
filename
string
必填
文件名要小于255个字符且不能包含以下任何字符："\/:*?|><。（注：不能重名）
containDir 为 true 时，传入路径+文件名，例如：/你好/123/测试文件.mp4
etag
string
必填
文件md5
size
number
必填
文件大小，单位为 byte 字节
duplicate
number
非必填
当有相同文件名时，文件处理策略（1保留两者，新文件名将自动添加后缀，2覆盖原文件）
containDir
bool
非必填
上传文件是否包含路径，默认false
返回数据 
名称
类型
是否必填
说明
fileID
number
非必填
文件ID。当123云盘已有该文件,则会发生秒传。此时会将文件ID字段返回。唯一
preuploadID
string
必填
预上传ID(如果 reuse 为 true 时,该字段不存在)
reuse
boolean
必填
是否秒传，返回true时表示文件已上传成功
sliceSize
number
必填
分片大小，必须按此大小生成文件分片再上传
servers
array
必填
上传地址


上传分片
API： POST   上传域名 + /upload/v2/file/slice
说明：
●
上传域名是创建文件接口响应中的servers
●
Content-Type: multipart/form-data
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
Body 参数
名称
类型
是否必填
说明
preuploadID
string
必填
预上传ID
sliceNo
number
必填
分片序号，从1开始自增
sliceMD5
string
必填
当前分片md5
slice
file
必填
分片二进制流
返回数据 
{
	"code": 0,
	"message": "ok",
	"data": null,
	"x-traceID": ""
}

上传完毕
API： POST   域名 + /upload/v2/file/upload_complete
说明：分片上传完成后请求
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
Body 参数
名称
类型
是否必填
说明
preuploadID
string
必填
预上传ID
返回数据
名称
类型
是否必填
说明
completed
bool
必填
上传是否完成
fileID
number
必填
上传完成文件id

获取上传域名
API： GET 域名 + /upload/v2/file/domain
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
Body 参数
无
返回数据 
名称
类型
是否必填
说明
data
array
必填
上传域名，存在多个可以任选其一

单个文件重命名
API：PUT 域名 + /api/v1/file/name
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
Body参数
名称
类型
是否必填
说明
fileId
number
是
文件id
fileName
string
是
文件名

批量文件重命名
API： POST 域名 + /api/v1/file/rename
说明：批量重命名文件，最多支持同时30个文件重命名
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
Body 参数
名称
类型
是否必填
说明
renameList
array
必填
数组,每个成员的格式为 文件ID|新的文件名

删除文件至回收站
API： POST 域名 + /api/v1/file/trash
说明：删除的文件，会放入回收站中
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
Body 参数
名称
类型
是否必填
说明
fileIDs
array
必填
文件id数组,一次性最大不能超过 100 个文件


彻底删除文件
API： POST 域名 + /api/v1/file/delete
说明：彻底删除文件前，文件必须要在回收站中，否则无法删除
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
Body 参数
名称
类型
是否必填
说明
fileIDs
array
必填
文件id数组,参数长度最大不超过 100

获取单个文件详情
API： GET 域名 + /api/v1/file/detail
说明：支持查询单文件夹包含文件大小
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
QueryString 参数
名称
类型
是否必填
说明
fileID
number
必填
文件ID
返回数据
名称
类型
是否必填
说明
fileID
number
必填
文件ID
filename
string
必填
文件名
type
number
必填
0-文件  1-文件夹
size
number
必填
文件大小
etag
string
必填
md5
status
number
必填
文件审核状态。 大于 100 为审核驳回文件
parentFileID
number
必填
父级ID
createAt
string
必填
文件创建时间
trashed
number
必填
该文件是否在回收站
0否、1是


获取多个文件详情
API：POST 域名 + /api/v1/file/infos
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
Body 参数
名称
类型
是否必填
说明
fileIds
[]number
是
文件id
返回数据
名称
类型
是否必填
说明
fileList
array
是

fileList[*].fileId
number
是
文件ID
fileList[*].filename
string
是
文件名
fileList[*].parentFileId
number
是
目录ID
fileList[*].type
number
是
0-文件  1-文件夹
fileList[*].etag
string
是
md5
fileList[*].size
number
是
文件大小
fileList[*].category
number
是
文件分类：0-未知 1-音频 2-视频 3-图片
fileList[*].status
number
是
文件审核状态。 大于 100 为审核驳回文件
fileList[*].punishFlag
number
是
惩罚标记
fileList[*].s3KeyFlag
string
是
关联s3_key的初始用户标识
fileList[*].storageNode
string
是
m0是ceph，m1以上为minio
fileList[*].trashed
number
是
是否在回收站：[0：否，1：是]
fileList[*].createAt
string
是
创建时间
fileList[*].updateAt
number
是
更新时间


获取文件列表（推荐）
API： GET 域名 + /api/v2/file/list
注意：此接口查询结果包含回收站的文件，需自行根据字段trashed判断处理
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
QueryString 参数
名称
类型
是否必填
说明
parentFileId
number
必填
文件夹ID，根目录传 0
limit
number
必填
每页文件数量，最大不超过100
searchData
string
选填
搜索关键字将无视文件夹ID参数。将会进行全局查找
searchMode
number
选填
0:全文模糊搜索(注:将会根据搜索项分词,查找出相似的匹配项)
1:精准搜索(注:精准搜索需要提供完整的文件名)

 lastFileId

number

选填

翻页查询时需要填写
返回数据
名称
类型
是否必填
说明
 lastFileId

number
必填
-1代表最后一页（无需再翻页查询）
其他代表下一页开始的文件id，携带到请求参数中
fileList
array
必填
文件列表

fileId
number
必填
文件Id

filename
string
必填
文件名

type
number
必填
0-文件  1-文件夹

size
number
必填
文件大小

etag
string
必填
md5

status
number
必填
文件审核状态。 大于 100 为审核驳回文件

parentFileId
number
必填
目录ID

category
number
必填
文件分类：0-未知 1-音频 2-视频 3-图片

trashed
int
必填
文件是否在回收站标识：0 否 1是


移动
API： POST 域名 + /api/v1/file/move
说明：批量移动文件，单级最多支持100个
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
Body 参数
名称
类型
是否必填
说明
fileIDs
array
必填
文件id数组
toParentFileID
number
必填
要移动到的目标文件夹id，移动到根目录时填写 0

下载
API：GET 域名 + /api/v1/file/download_info
Header 参数
名称
类型
是否必填
说明
Authorization
string
必填
鉴权access_token
Platform
string
必填
固定为:open_platform
QueryString 参数
名称
类型
是否必填
说明
fileId
number
是
文件id
返回数据
名称
类型
是否必填
说明
downloadUrl
string
是
下载地址
异常返回
code
异常原因
示例message
5113
自用下载流量不足
您今日自用下载流量已超出1GB上限，升级VIP会员可无限流量下载  
5066
文件不存在
文件不存在，检查传入fileId是否正确

