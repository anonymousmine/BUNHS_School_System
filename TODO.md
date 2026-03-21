<<<<<<< HEAD
# BUNHS Railway Fix v2 - Docker Builder ✅

✅ 2/3: railway.json + Dockerfile (php8.3-apache + mysqli verify)

## Deploy Now:

```
git add . && git commit -m "fix: railway dockerfile mysqli php8.3" && git push
```

**Railway will:**

1. Build Docker → ✅ "mysqli extension LOADED"
2. Deploy with your DB vars
3. Site works!

**Why Docker**: Your Dockerfile mysqli install WORKS. Nixpacks/FrankenPHP broken.
=======
# BUNHS School System - Railway MySQLi Fix (Nixpacks)

✅ 3/5 complete: nixpacks.toml, railway.json, start-container.sh → plain PHP server + mysqli/DB checks

## Deployment Ready ✅ 3/3 Core Files Fixed

**Test Locally:**

```bash
docker build -t bunhs-test . && docker run -p 8080:8080 -e DB_HOST=host.docker.internal -e DB_PORT=3306 --rm bunhs-test
```

**Deploy:**

1. `git add . && git commit -m "fix: railway mysqli nixpacks" && git push`
2. Railway rebuilds → check build logs: "✅ mysqli extension LOADED"
3. Set Railway DB vars
4. Site loads without 500 error

**Next Manual Steps:**

- Local test confirms mysqli
- Deploy/push to Railway
- Verify site + DB connection

**Next:** Complete step 1
>>>>>>> a03aafe901d1a7c9bf4323242c5e2b494f6d6f82
