-- Copyright (c) 2013 Christophe-Marie Duquesne <chmd@chmd.fr>
--
-- Permission is hereby granted, free of charge, to any person obtaining a
-- copy of this software and associated documentation files (the
-- "Software"), to deal in the Software without restriction, including
-- without limitation the rights to use, copy, modify, merge, publish,
-- distribute, sublicense, and/or sell copies of the Software, and to
-- permit persons to whom the Software is furnished to do so, subject to
-- the following conditions:
--
-- The above copyright notice and this permission notice shall be included
-- in all copies or substantial portions of the Software.
--
-- THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
-- OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
-- MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
-- IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
-- CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
-- TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
-- SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
--
--
-- Note for debian users:
--
-- This script requires luacrypto and lbase64. Unfortunately, none of
-- those are currently (2013-07-25) available as debian packages. There is
-- another implementation of base64 available in luasocket, but it seemed
-- a bit overkill to make it a requirement while it is available in a
-- smaller package. There is no other implementation of hmac-sha1 that I
-- know of available as a debian package. If one is made, please let me
-- know, I'll port this script so it can be used with dependencies that
-- are available on debian.
--
-- As an alternative to debian packages, you can use luarocks to download
-- and install these dependencies:
--
-- sudo aptitude install luarocks
-- sudo luarocks install luacrypto
-- sudo luarocks install lbase64
--
-- If everything is installed correctly and the path of luarocks are
-- correctly setup (which should be automatic), this script will work.
-- Check the lighty log if it does not.

require "luarocks.loader"
local crypto = require("crypto")
local hmac = require("crypto.hmac")
local base64 = require("base64")

-- {{ Default config
config = { }

-- Where to redirect your users if the token is not valid.
config["login_url"] = "/login.php"
-- or config["login_url"] = "https://login.example.com"

-- How long (in seconds) is a token considered valid
config["token_validity"] = 86400 -- 1 day

-- Which identities are authorized. If nil, then everyone is authorized.
-- config["authorized_identities"] = { }
-- config["authorized_identities"]["user1"] = true
-- config["authorized_identities"]["user2"] = true

-- Under which name to store the access token in the cookie. Change it if
-- the content you protect with this script is already using this field in
-- its own cookies.
config["access_token"] = "access_token"

-- Under which name to store your openid identity in the cookie. Change it
-- if the content you protect with this script is already using this field
-- in its own cookies.
config["identity"] = "identity"

-- Where to put the generated secret. Change it if this path if not
-- writable by lighttpd. It should also be readable by your login page
config["secret_file"] = "/var/run/lighttpd/openid_seed"

--}}

-- {{ Config loader

-- function from http://lua-users.org/wiki/DofileNamespaceProposal
function import(name)
    local f,e = loadfile(name)
    if not f then error(e, 2) end
    setfenv(f, getfenv(2))
    return f()
end

-- }}

--{{ Core functions

-- Returns the hmac_sha1 of the message, signed with the key, then base64
-- encoded (This is the only function that requires external modules).
function base64_hmac_sha1(key, message)
    return base64.encode(hmac.digest("sha1", message, key, true))
end

-- Returns a random string of specified length (default = 40)
-- characters are taken in [0-9a-zA-Z]
function random_string(len)
    len = len or 40
    local choices = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"
    local res = ''
    for i = 1,len do
        r = math.random(1, choices:len())
        res = res .. choices:sub(r, r)
    end
    return res
end

-- Return true if file exists and is readable.
function file_exists(path)
    local file = io.open(path, "rb")
    if file then file:close() end
    return file ~= nil
end

-- Create the secret file and write a random string in it
function init_secret_file()
    local file = io.open(config["secret_file"], "w")
    file:write(random_string())
    file:close()
end

-- Get the secret
function get_secret()
    -- Create the secret file if necessary
    if not file_exists(config["secret_file"]) then
        init_secret_file()
    end
    -- Read the secret from the first line of this file
    local file = io.open(config["secret_file"])
    local result = file:read("*line")
    file:close()
    return result
end

-- Splits a string, given a split pattern
-- Adapted from http://www.coronalabs.com/blog/2013/04/16/lua-string-magic/
function string:split(split_pattern, result)
    result = result or { }
    local start = 1
    local split_start, split_end = self:find(split_pattern, start)
    while split_start do
        table.insert(result, self:sub(start, split_start-1))
        start = split_end + 1
        split_start, split_end = self:find(split_pattern, start)
    end
    table.insert(result, self:sub(start))
    return result
end

-- Parse the cookie string into a lua table
-- The result is a table: cookie[key] = value
function get_cookie()
    local cookie = { }
    local cookie_string = lighty.request['Cookie'] or ""
    for _,field in pairs(cookie_string:split("; ")) do
        local i = field:find('=')
        if i then
            local key = field:sub(1, i - 1)
            local value = field:sub(i + 1)
            cookie[key] = value
        else
            cookie[field] = nil
        end
    end
    return cookie
end

-- Decodes an url-encoded string
-- From http://lua-users.org/wiki/StringRecipes
function url_decode(str)
    str = string.gsub (str, "+", " ")
    str = string.gsub (str, "%%(%x%x)",
    function(h) return string.char(tonumber(h,16)) end)
    str = string.gsub (str, "\r\n", "\n")
    return str
end

-- Url-encodes a string
-- From http://lua-users.org/wiki/StringRecipes
function url_encode(str)
    if (str) then
        str = string.gsub (str, "\n", "\r\n")
        str = string.gsub (str, "([^%w %-%_%.%~])",
        function (c) return string.format ("%%%02X", string.byte(c)) end)
        str = string.gsub (str, " ", "+")
    end
    return str
end

-- Returns true if the access token is valid
function valid_access_token()
    local cookie = get_cookie()
    -- get the timestamp
    local t = os.time()
    local timestamp = tostring(t - t % config["token_validity"])
    -- extract cookie values
    local identity = url_decode(cookie[config["identity"]] or "")
    local access_token = url_decode(cookie[config["access_token"]] or "")
    -- compute valid token
    local message = identity .. timestamp
    local secret = get_secret()
    local hash = base64_hmac_sha1(secret, message)

    -- Useful for debugging your login page
    print("Valid access_token: " .. hash)
    print("The cookie provided: " .. access_token)

    -- compare with provided token
    return hash == access_token
end

-- Returns the requested url
function req_url()
    local res = "https://"
    res = res .. lighty.env["uri.authority"]
    res = res .. lighty.env["request.uri"]
    return res
end
--}}

--{{ script logic

-- Load user config
config_file = lighty.req_env["EXTERNAL_AUTH_CONFIG"]
if config_file ~= nil and config_file ~= '' then
    -- if the server returns an error 500, it is probably because this
    -- file does not exist or is not valid lua. In any case, it is better
    -- to have an error than to continue running. We let this script
    -- crash.
    import(config_file)
end

-- Force https
if (lighty.env["uri.scheme"] == "http") then
    lighty.header["Location"] = "https://" .. lighty.env["uri.authority"] .. lighty.env["request.uri"]
    return 302
end

-- If the token is not valid, redirect to the login page
if not valid_access_token() then
    local url = config["login_url"]
    url = url .. "?orig_url=" .. url_encode(req_url())
    lighty.header["Location"] = url
    return 302
end

-- Otherwise, check the user identity
local cookie = get_cookie()
local identity = url_decode(cookie[config["identity"]] or "")
if config["authorized_identities"] then
    -- The login page confirms the identity, but the area is restricted.
    if not config["authorized_identities"][identity] then
        -- The user is presented a 403 page
        local orig_url = req_url()
        local login_url = config["login_url"]
        login_url = login_url .. "?orig_url=" .. url_encode(orig_url)
        lighty.header["Content-Type"] = "text/html"
        lighty.content = {
            "<h1>Access denied!</h1>",
            "<p>You are recognized as ",
            "<code>" .. identity .. "</code>, ",
            "but this user is not allowed to see ",
            "<a href='" .. orig_url .. "'>" .. orig_url .. "</a>.</p>",
            "<p>In order to fix this issue, you should visit ",
            "<a href='" .. login_url .. "'>" .. config["login_url"] .. "</a> ",
            "and log in as a different user.</p>"
        }
        print("Access denied: " .. identity)
        return 403
    end
end

-- Starting from lighttpd 1.4.33, it is possible to set REMOTE_USER via
-- the lua interface. However, it is not possible to simply detect the
-- lighttpd version from lua. So you may want to manually uncomment the
-- following lines if your version of lighty is >= 1.4.33. Note that this
-- was never actually tested, so use at your own risk!
-- lighty.req_env["REMOTE_USER"] = identity

--}}
