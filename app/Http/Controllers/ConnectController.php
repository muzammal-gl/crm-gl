<?php

namespace App\Http\Controllers;

use App\Models\Connect;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ConnectController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if($user->role === 'admin'){
            $connects = Connect::with('user')->orderBy('date', 'asc')->get();
            $leads = Lead::all();
        } else {
            $connects = Connect::where('user_id', $user->id)->orderBy('date', 'asc')->get();
            $leads = Lead::where('user_id', $user->id)->get();
        }
    
        $groupedByWeekConnects = $connects->groupBy(function ($item) {
            return \Carbon\Carbon::parse($item->date)->startOfWeek()->format('Y-m-d') . ' - ' . \Carbon\Carbon::parse($item->date)->endOfWeek()->format('Y-m-d');
        });

        $groupedByWeekLeads = $leads->groupBy(function ($item) {
            return \Carbon\Carbon::parse($item->date)->startOfWeek()->format('Y-m-d') . ' - ' . \Carbon\Carbon::parse($item->date)->endOfWeek()->format('Y-m-d');
        });
        
        $weeklyData = [];
        $carryForward = 0;
        $remainingConnects = 0;
    
        foreach ($groupedByWeekConnects as $week => $connects) {
            $totalBuy = $connects->sum('connects_buy');
            $totalSpent = isset($groupedByWeekLeads[$week]) ? $groupedByWeekLeads[$week]->filter(fn($lead) => is_numeric($lead['connects_spent']))->sum('connects_spent'): 0;

           $remainingConnects = max(0, $totalBuy - $totalSpent + $carryForward);

            $weeklyData[$week] = [
                'total_buy' => $totalBuy,
                'total_spent' => $totalSpent,
                'carry_forward' => $remainingConnects > 0 ? $remainingConnects : 0,
            ];

            $carryForward = $weeklyData[$week]['carry_forward'];
        }

        return view('connect.index', compact('groupedByWeekConnects', 'groupedByWeekLeads', 'connects' ,'weeklyData','remainingConnects'));
    }

     public function Userindex()
    {
        $user = Auth::user();
    
        $connects = Connect::with('user')
            ->orderBy('user_id', 'asc')
            ->orderBy('date', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id . '-' . $item->date;
            })->map(function ($groupedItems) {
                return (object) [
                    'user' => $groupedItems->first()->user,
                    'user_id' => $groupedItems->first()->user_id,
                    'date' => $groupedItems->first()->date,
                    'connects_buy' => $groupedItems->sum('connects_buy'),
                    'connects_spent' => $groupedItems->sum('connects_spent'),
                    'price' => $groupedItems->sum('price'),
                ];
            });
    
        $leads = Lead::all();
        $remainingConnects = [];
    
        foreach ($connects as $connect) {
            $totalBuy = $connect->connects_buy;
            $totalSpent = $leads
                ->where('user_id', $connect->user_id)
                ->whereBetween('created_at', [
                    \Carbon\Carbon::parse($connect->date)->startOfDay(),
                    \Carbon\Carbon::parse($connect->date)->endOfDay()
                ])
                ->sum('connects_spent');
    
            $remainingConnects[$connect->user_id][$connect->date] = max(0, $totalBuy - $totalSpent);
        }
    
        return view('connect.users_connect', compact('connects', 'leads', 'remainingConnects', 'user'));
    }

    public function store(Request $request){
      
        $userid = Auth::id();
        $validatedData = $request->validate([
            'date' => 'required|date',
            'price' => 'required|numeric',
            'connects_buy' => 'required|integer',
        ]);

        Connect::create([
            'user_id' => $userid,
            'date' => $validatedData['date'],
            'price' => $validatedData['price'],
            'connects_buy' => $validatedData['connects_buy'],
        ]);
        return redirect()->route('connect.index')->with('success', 'Connect added successfully.');
    }
     
    public function edit(string $id)
    {
        $editconnect = Connect::findOrFail($id);
        return response()->json([
            'success' => true,
            'connect' => $editconnect
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'date' => 'required|date',
            'price' => 'required|numeric',
            'connects_buy' => 'required|integer',
        ]);

    $connect = Connect::findOrFail($id);
    $connect->update($validatedData);
    return redirect()->route('connect.index')->with('success', 'Connect updated successfully');
    }

    public function destroy(string $id)
    {
        $connect = Connect::findOrFail($id);
        $connect->delete();
        return redirect()->route('connect.index')->with('success', 'Connect deleted successfully');
    } 
}
