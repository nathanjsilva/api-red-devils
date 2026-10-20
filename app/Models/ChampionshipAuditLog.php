<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipAuditLog extends Model { protected $fillable=['championship_id','user_id','action','auditable_type','auditable_id','payload']; protected $casts=['payload'=>'array']; }
